"""
OperationModule Database MCP Server
Gives Claude direct read-only access to the SQLite database so queries
don't require reading PHP files or running artisan commands.
"""

import sqlite3
import json
import sys
import os
from pathlib import Path

# ── MCP SDK ──────────────────────────────────────────────────────────────────
from mcp.server.fastmcp import FastMCP

DB_PATH = Path(__file__).parent.parent / "database" / "database.sqlite"

mcp = FastMCP("OperationModule DB")


# ─────────────────────────────────────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────────────────────────────────────

def _conn() -> sqlite3.Connection:
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    return conn


def _rows(sql: str, params: tuple = ()) -> list[dict]:
    with _conn() as conn:
        cur = conn.execute(sql, params)
        return [dict(r) for r in cur.fetchall()]


def _one(sql: str, params: tuple = ()) -> dict | None:
    rows = _rows(sql, params)
    return rows[0] if rows else None


# ─────────────────────────────────────────────────────────────────────────────
# Schema & Meta
# ─────────────────────────────────────────────────────────────────────────────

@mcp.tool()
def list_tables() -> str:
    """List all tables in the database with row counts."""
    rows = _rows("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
    result = []
    with _conn() as conn:
        for r in rows:
            t = r["name"]
            count = conn.execute(f"SELECT COUNT(*) FROM [{t}]").fetchone()[0]
            result.append(f"{t}: {count} rows")
    return "\n".join(result)


@mcp.tool()
def describe_table(table: str) -> str:
    """Return the column definitions for a table."""
    rows = _rows(f"PRAGMA table_info([{table}])")
    if not rows:
        return f"Table '{table}' not found."
    lines = [f"Table: {table}"]
    for c in rows:
        nullable = "" if c["notnull"] else " NULL"
        default  = f" DEFAULT {c['dflt_value']}" if c["dflt_value"] is not None else ""
        pk       = " PK" if c["pk"] else ""
        lines.append(f"  {c['name']} {c['type']}{nullable}{default}{pk}")
    return "\n".join(lines)


@mcp.tool()
def run_query(sql: str) -> str:
    """
    Execute a read-only SQL query and return results as JSON.
    Only SELECT statements are allowed. Max 200 rows returned.
    """
    stripped = sql.strip().upper()
    if not stripped.startswith("SELECT") and not stripped.startswith("WITH"):
        return "Error: only SELECT / WITH queries are permitted."
    try:
        rows = _rows(sql)
        if len(rows) > 200:
            rows = rows[:200]
            suffix = "\n[Truncated to 200 rows]"
        else:
            suffix = ""
        return json.dumps(rows, default=str, indent=2) + suffix
    except Exception as e:
        return f"Error: {e}"


# ─────────────────────────────────────────────────────────────────────────────
# Purchase Module
# ─────────────────────────────────────────────────────────────────────────────

@mcp.tool()
def get_suppliers(active_only: bool = False) -> str:
    """List all suppliers with their contact details."""
    where = "WHERE is_active = 1" if active_only else ""
    rows = _rows(f"""
        SELECT id, name, contact_person, email, phone, address, tax_number,
               CASE WHEN is_active THEN 'Active' ELSE 'Inactive' END AS status
        FROM suppliers {where}
        ORDER BY name
    """)
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_purchase_orders(status: str = "") -> str:
    """
    List purchase orders. Optional status filter: draft|sent|partial|received|cancelled
    """
    where = "WHERE po.status = ?" if status else ""
    params = (status,) if status else ()
    rows = _rows(f"""
        SELECT po.id, po.po_number, s.name AS supplier, po.po_date,
               po.expected_delivery_date, po.total_amount, po.status
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        {where}
        ORDER BY po.po_date DESC
        LIMIT 100
    """, params)
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_purchase_requests(status: str = "") -> str:
    """
    List purchase requests. Optional status: pending|approved|rejected|ordered
    """
    where = "WHERE pr.status = ?" if status else ""
    params = (status,) if status else ()
    rows = _rows(f"""
        SELECT pr.id, pr.request_number, pr.date, pr.department,
               pr.requested_by_name, pr.required_date_text, pr.status,
               COUNT(pri.id) AS item_count
        FROM purchase_requests pr
        LEFT JOIN purchase_request_items pri ON pri.purchase_request_id = pr.id
        {where}
        GROUP BY pr.id
        ORDER BY pr.date DESC
        LIMIT 100
    """, params)
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_supplier_invoices(status: str = "") -> str:
    """List supplier invoices. Optional status: unpaid|partial|paid"""
    where = "WHERE si.status = ?" if status else ""
    params = (status,) if status else ()
    rows = _rows(f"""
        SELECT si.id, si.invoice_number, s.name AS supplier,
               si.invoice_date, si.due_date, si.total_amount,
               si.paid_amount, si.status
        FROM supplier_invoices si
        JOIN suppliers s ON s.id = si.supplier_id
        {where}
        ORDER BY si.invoice_date DESC
        LIMIT 100
    """, params)
    return json.dumps(rows, default=str, indent=2)


# ─────────────────────────────────────────────────────────────────────────────
# Inventory Module
# ─────────────────────────────────────────────────────────────────────────────

@mcp.tool()
def get_items(category: str = "", active_only: bool = False) -> str:
    """
    List inventory items.
    category: raw_material | wip | finished_good (blank = all)
    """
    conditions = []
    params: list = []
    if category:
        conditions.append("category = ?")
        params.append(category)
    if active_only:
        conditions.append("is_active = 1")
    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    rows = _rows(f"""
        SELECT id, item_code, item_name, category, unit_of_measure,
               cost_price, minimum_stock_level,
               CASE WHEN is_active THEN 'Active' ELSE 'Inactive' END AS status
        FROM items {where}
        ORDER BY category, item_name
    """, tuple(params))
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_stock_levels(warehouse_id: int = 0, low_stock_only: bool = False) -> str:
    """
    Current stock levels, optionally filtered by warehouse or showing only low-stock items.
    """
    conditions = []
    params: list = []
    if warehouse_id:
        conditions.append("sl.warehouse_id = ?")
        params.append(warehouse_id)
    if low_stock_only:
        conditions.append("sl.quantity <= i.minimum_stock_level")
    where = ("WHERE " + " AND ".join(conditions)) if conditions else ""
    rows = _rows(f"""
        SELECT i.item_code, i.item_name, i.category, i.unit_of_measure,
               w.name AS warehouse, sl.quantity,
               i.minimum_stock_level,
               ROUND(sl.quantity * i.cost_price, 2) AS stock_value
        FROM stock_levels sl
        JOIN items i ON i.id = sl.item_id
        JOIN warehouses w ON w.id = sl.warehouse_id
        {where}
        ORDER BY i.category, i.item_name
    """, tuple(params))
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_warehouses() -> str:
    """List all warehouses."""
    rows = _rows("""
        SELECT w.id, w.code, w.name, w.location,
               CASE WHEN w.is_active THEN 'Active' ELSE 'Inactive' END AS status,
               COUNT(sl.id) AS item_types,
               ROUND(SUM(sl.quantity * i.cost_price), 2) AS total_value
        FROM warehouses w
        LEFT JOIN stock_levels sl ON sl.warehouse_id = w.id
        LEFT JOIN items i ON i.id = sl.item_id
        GROUP BY w.id
        ORDER BY w.name
    """)
    return json.dumps(rows, default=str, indent=2)


# ─────────────────────────────────────────────────────────────────────────────
# Production Module
# ─────────────────────────────────────────────────────────────────────────────

@mcp.tool()
def get_production_orders(status: str = "") -> str:
    """
    List production orders.
    status: planned|in_progress|completed|cancelled
    """
    where = "WHERE po.status = ?" if status else ""
    params = (status,) if status else ()
    rows = _rows(f"""
        SELECT po.id, po.order_number, i.item_name AS product,
               po.quantity_to_produce, po.quantity_produced,
               po.production_date, po.completion_date, po.status
        FROM production_orders po
        JOIN items i ON i.id = po.product_id
        {where}
        ORDER BY po.production_date DESC
        LIMIT 100
    """, params)
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_bill_of_materials(product_id: int = 0) -> str:
    """
    Show bill of materials (recipes). Pass product_id to filter to one product.
    """
    where = "WHERE bom.product_id = ?" if product_id else ""
    params = (product_id,) if product_id else ()
    rows = _rows(f"""
        SELECT p.item_name AS product, r.item_name AS raw_material,
               bom.quantity_required, bom.unit_of_measure
        FROM bill_of_materials bom
        JOIN items p ON p.id = bom.product_id
        JOIN items r ON r.id = bom.raw_material_id
        {where}
        ORDER BY p.item_name, r.item_name
    """, params)
    return json.dumps(rows, default=str, indent=2)


# ─────────────────────────────────────────────────────────────────────────────
# Sales Module
# ─────────────────────────────────────────────────────────────────────────────

@mcp.tool()
def get_customers(active_only: bool = False) -> str:
    """List all customers with outstanding balances."""
    where = "WHERE is_active = 1" if active_only else ""
    rows = _rows(f"""
        SELECT id, name, contact_person, email, phone,
               credit_limit, outstanding_balance,
               CASE WHEN is_active THEN 'Active' ELSE 'Inactive' END AS status
        FROM customers {where}
        ORDER BY name
    """)
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_sales_orders(status: str = "") -> str:
    """
    List sales orders.
    status: draft|confirmed|dispatched|invoiced|cancelled
    """
    where = "WHERE so.status = ?" if status else ""
    params = (status,) if status else ()
    rows = _rows(f"""
        SELECT so.id, so.order_number, c.name AS customer,
               so.order_date, so.delivery_date,
               so.total_amount, so.status
        FROM sales_orders so
        JOIN customers c ON c.id = so.customer_id
        {where}
        ORDER BY so.order_date DESC
        LIMIT 100
    """, params)
    return json.dumps(rows, default=str, indent=2)


@mcp.tool()
def get_sales_invoices(status: str = "") -> str:
    """List sales invoices. status: unpaid|partial|paid|cancelled"""
    where = "WHERE si.status = ?" if status else ""
    params = (status,) if status else ()
    rows = _rows(f"""
        SELECT si.id, si.invoice_number, c.name AS customer,
               si.invoice_date, si.due_date, si.total_amount,
               si.paid_amount, si.status
        FROM sales_invoices si
        JOIN customers c ON c.id = si.customer_id
        {where}
        ORDER BY si.invoice_date DESC
        LIMIT 100
    """, params)
    return json.dumps(rows, default=str, indent=2)


# ─────────────────────────────────────────────────────────────────────────────
# Dashboard / Summary
# ─────────────────────────────────────────────────────────────────────────────

@mcp.tool()
def get_dashboard_summary() -> str:
    """
    High-level KPIs: inventory value, open POs, open sales orders,
    unpaid supplier invoices, unpaid sales invoices, active items/suppliers.
    """
    with _conn() as conn:
        def scalar(sql, p=()):
            r = conn.execute(sql, p).fetchone()
            return r[0] if r else 0

        summary = {
            "suppliers":              scalar("SELECT COUNT(*) FROM suppliers WHERE is_active=1"),
            "items_active":           scalar("SELECT COUNT(*) FROM items WHERE is_active=1"),
            "inventory_value":        scalar("SELECT ROUND(SUM(sl.quantity*i.cost_price),2) FROM stock_levels sl JOIN items i ON i.id=sl.item_id"),
            "low_stock_items":        scalar("SELECT COUNT(*) FROM stock_levels sl JOIN items i ON i.id=sl.item_id WHERE sl.quantity<=i.minimum_stock_level AND i.minimum_stock_level>0"),
            "open_purchase_orders":   scalar("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft','sent','partial')"),
            "pending_requests":       scalar("SELECT COUNT(*) FROM purchase_requests WHERE status='pending'"),
            "open_production_orders": scalar("SELECT COUNT(*) FROM production_orders WHERE status IN ('planned','in_progress')"),
            "open_sales_orders":      scalar("SELECT COUNT(*) FROM sales_orders WHERE status IN ('draft','confirmed')"),
            "unpaid_supplier_invoices": scalar("SELECT ROUND(SUM(total_amount-paid_amount),2) FROM supplier_invoices WHERE status IN ('unpaid','partial')"),
            "unpaid_sales_invoices":    scalar("SELECT ROUND(SUM(total_amount-paid_amount),2) FROM sales_invoices WHERE status IN ('unpaid','partial')"),
        }
    return json.dumps(summary, default=str, indent=2)


# ─────────────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    mcp.run(transport="stdio")
