@extends('layouts.app')

@section('title', 'New Goods Receipt Note')

@section('content')
<div class="mb-6">
    <h1 class="page-title">New Goods Receipt Note</h1>
    <p class="page-subtitle"><a href="{{ route('purchase.grns.index') }}" class="text-blue-600 hover:underline">GRNs</a> / New</p>
</div>

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('purchase.grns.store') }}" method="POST">
    @csrf

    <div class="card card-body mb-4">
        <h2 class="text-base font-semibold text-gray-700 mb-4">GRN Details</h2>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Purchase Order <span class="text-red-500">*</span></label>
                <select name="purchase_order_id" id="po-select" required onchange="loadPOItems(this)" class="form-select">
                    <option value="">-- Select PO --</option>
                    @foreach($purchaseOrders as $po)
                        <option value="{{ $po->id }}"
                                data-items="{{ json_encode($po->items) }}"
                                {{ old('purchase_order_id') == $po->id ? 'selected' : '' }}>
                            {{ $po->po_number ?? 'PO-' . str_pad($po->id, 5, '0', STR_PAD_LEFT) }} - {{ $po->supplier->name ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Warehouse <span class="text-red-500">*</span></label>
                <select name="warehouse_id" required class="form-select">
                    <option value="">-- Select Warehouse --</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Received Date <span class="text-red-500">*</span></label>
                <input type="date" name="received_date" value="{{ old('received_date', date('Y-m-d')) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-textarea">{{ old('notes') }}</textarea>
            </div>

        </div>
    </div>

    <!-- Items from PO -->
    <div class="card card-body mb-4">
        <h2 class="text-base font-semibold text-gray-700 mb-4">Items Received</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="pb-2 text-left font-semibold text-gray-600">Item</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">PO Qty</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">Qty Received</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-36">Type</th>
                    </tr>
                </thead>
                <tbody id="grn-items-body">
                    <tr id="no-items-row">
                        <td colspan="3" class="py-4 text-center text-gray-400">Select a Purchase Order to load items.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">Save GRN</button>
        <a href="{{ route('purchase.grns.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>

<script>
function setGrnType(idx, val) {
    document.getElementById('grn-type-' + idx).value = val;
    var inv = document.getElementById('grn-t-inv-' + idx);
    var con = document.getElementById('grn-t-con-' + idx);
    if (val === 'inventory') {
        inv.style.background = '#eff6ff'; inv.style.color = '#2563eb';
        con.style.background = '#fff';    con.style.color = '#94a3b8';
    } else {
        con.style.background = '#fef3c7'; con.style.color = '#92400e';
        inv.style.background = '#fff';    inv.style.color = '#94a3b8';
    }
}

function loadPOItems(select) {
    var tbody = document.getElementById('grn-items-body');
    var option = select.options[select.selectedIndex];
    tbody.innerHTML = '';

    if (!option.value) {
        tbody.innerHTML = '<tr id="no-items-row"><td colspan="4" class="py-4 text-center text-gray-400">Select a Purchase Order to load items.</td></tr>';
        return;
    }

    var items = JSON.parse(option.dataset.items || '[]');
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-400">No items on this PO.</td></tr>';
        return;
    }

    items.forEach(function(item, idx) {
        var row = document.createElement('tr');
        row.className = 'border-b border-gray-100';
        row.innerHTML = `
            <td class="py-2 pr-2 text-gray-800">${item.item ? item.item.item_name : 'Item #' + item.item_id}
                <input type="hidden" name="items[${idx}][item_id]" value="${item.item_id}">
            </td>
            <td class="py-2 pr-2 text-gray-500">${item.quantity}</td>
            <td class="py-2 pr-2">
                <input type="number" name="items[${idx}][quantity_received]"
                       value="${item.quantity}" min="0" step="0.01"
                       max="${item.quantity}"
                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
            </td>
            <td class="py-2">
                <div style="display:flex;gap:0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;width:fit-content;">
                    <span id="grn-t-inv-${idx}" onclick="setGrnType(${idx},'inventory')"
                        style="padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;background:#eff6ff;color:#2563eb;white-space:nowrap;">
                        Inventory
                    </span>
                    <span id="grn-t-con-${idx}" onclick="setGrnType(${idx},'consumable')"
                        style="padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;white-space:nowrap;">
                        Consumable
                    </span>
                </div>
                <input type="hidden" name="items[${idx}][type]" id="grn-type-${idx}" value="inventory">
            </td>`;
        tbody.appendChild(row);
    });
}
</script>
@endsection
