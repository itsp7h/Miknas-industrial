@extends('layouts.app')

@section('title', 'Inventory Items')

@section('content')
<div style="box-sizing:border-box;width:100%;">

    {{-- Hero header --}}
    <div style="box-sizing:border-box;width:100%;padding:20px 18px;color:#fff;position:relative;overflow:hidden;border-radius:18px;background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%);">
        <div style="position:absolute;top:-32px;right:-32px;width:144px;height:144px;border-radius:9999px;background:rgba(255,255,255,.1);"></div>
        <div style="position:relative;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
            <div style="min-width:0;">
                <p style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7);margin:0;">Inventory</p>
                <h1 style="font-size:22px;font-weight:800;margin:2px 0 0;line-height:1.2;">Items</h1>
                <p style="font-size:13px;color:rgba(255,255,255,.85);margin:4px 0 0;">{{ $items->count() }} total</p>
            </div>
            <a href="{{ route('inventory.items.create') }}" style="flex-shrink:0;background:#fff;color:#2563eb;border:0;border-radius:12px;padding:10px 14px;font-size:13px;font-weight:700;white-space:nowrap;text-decoration:none;">
                + Add
            </a>
        </div>
    </div>

    {{-- Search --}}
    <div style="margin:12px 0 4px;">
        <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:11px 14px;">
            <svg width="15" height="15" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35"/>
            </svg>
            <input id="item-search" type="text" placeholder="Search items…" aria-label="Search items"
                   style="flex:1;min-width:0;border:0;outline:none;font-size:14px;background:transparent;">
        </div>
        <p id="item-search-count" style="display:none;font-size:11px;color:#94a3b8;margin:6px 4px 0;"></p>
    </div>

    {{-- Secondary actions --}}
    <div style="display:flex;gap:8px;margin:10px 0 12px;overflow-x:auto;padding-bottom:2px;">
        <a href="{{ route('inventory.items.export-pdf') }}" style="flex-shrink:0;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#475569;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;text-decoration:none;white-space:nowrap;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6M9 17h4"/></svg>
            Export PDF
        </a>
        <a href="{{ route('inventory.items.template') }}" style="flex-shrink:0;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#475569;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;text-decoration:none;white-space:nowrap;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Template
        </a>
        <button type="button" onclick="document.getElementById('import-sheet').classList.add('open')" style="flex-shrink:0;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#15803d;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:8px 12px;white-space:nowrap;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import Excel
        </button>
    </div>

    {{-- Item cards --}}
    <div style="display:flex;flex-direction:column;gap:10px;padding-bottom:16px;">
        @forelse($items as $item)
        @php
            $catBadge = match($item->category) {
                'raw_material'  => ['bg' => '#dbeafe', 'fg' => '#1d4ed8', 'label' => 'Raw Material'],
                'wip'           => ['bg' => '#fef9c3', 'fg' => '#854d0e', 'label' => 'WIP'],
                'finished_good' => ['bg' => '#dcfce7', 'fg' => '#15803d', 'label' => 'Finished Good'],
                default         => ['bg' => '#f1f5f9', 'fg' => '#64748b', 'label' => ucfirst($item->category)],
            };
        @endphp
        <div class="data-row" style="background:#fff;border:1px solid #f1f5f9;border-radius:16px;padding:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                <div style="min-width:0;">
                    <div style="font-weight:700;font-size:14px;color:#0f172a;">{{ $item->item_name }}</div>
                    <div style="font-size:12px;color:#94a3b8;font-family:monospace;margin-top:2px;">{{ $item->item_code }}</div>
                </div>

                <div style="flex-shrink:0;display:flex;align-items:center;gap:6px;">
                    <span style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;background:{{ $item->is_active ? '#dcfce7' : '#f1f5f9' }};color:{{ $item->is_active ? '#15803d' : '#64748b' }};">
                        {{ $item->is_active ? 'Active' : 'Inactive' }}
                    </span>

                    {{-- Combined action button — replaces separate Edit/Delete buttons --}}
                    <div style="position:relative;">
                        <button type="button" onclick="toggleItemMenu({{ $item->id }}, event)" aria-label="Actions"
                                style="width:30px;height:30px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;color:#64748b;display:flex;align-items:center;justify-content:center;">
                            <svg width="15" height="15" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 100-4 2 2 0 000 4zM10 12a2 2 0 100-4 2 2 0 000 4zM10 18a2 2 0 100-4 2 2 0 000 4z"/></svg>
                        </button>
                        <div id="item-menu-{{ $item->id }}" class="item-menu" style="display:none;position:absolute;top:36px;right:0;z-index:20;min-width:140px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);overflow:hidden;">
                            <a href="{{ route('inventory.items.edit', $item) }}" style="display:flex;align-items:center;gap:8px;padding:11px 14px;font-size:13px;font-weight:600;color:#334155;text-decoration:none;border-bottom:1px solid #f1f5f9;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>
                            <form action="{{ route('inventory.items.destroy', $item) }}" method="POST"
                                  onsubmit="closeAllItemMenus(); confirmDelete(this,'Delete this item?','This inventory item will be permanently removed.'); return false;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="display:flex;align-items:center;gap:8px;width:100%;padding:11px 14px;font-size:13px;font-weight:600;color:#dc2626;background:none;border:none;text-align:left;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                <span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:8px;background:{{ $catBadge['bg'] }};color:{{ $catBadge['fg'] }};">{{ $catBadge['label'] }}</span>
                <span style="font-size:11px;font-weight:600;padding:3px 9px;border-radius:8px;background:#f8fafc;color:#64748b;">{{ $item->unit_of_measure }}</span>
            </div>

            <div style="font-size:12px;color:#64748b;margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9;">
                Min Stock: <strong style="color:#0f172a;">{{ number_format($item->minimum_stock_level, 2) }}</strong>
                &nbsp;·&nbsp;
                Cost: <strong style="color:#0f172a;">{{ number_format($item->cost_price, 2) }}</strong>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:40px 16px;color:#94a3b8;">
            <p style="font-size:28px;margin:0;">📦</p>
            <p style="font-size:13px;margin:8px 0 0;">No items found.</p>
        </div>
        @endforelse

        <div id="item-no-results" style="display:none;text-align:center;padding:40px 16px;color:#94a3b8;">
            <p style="font-size:28px;margin:0;">🔍</p>
            <p style="font-size:13px;margin:8px 0 0;">No items match your search.</p>
        </div>
    </div>
</div>

{{-- ═══════════ Import bottom sheet ═══════════ --}}
<div id="import-sheet" class="mobile-sheet" role="dialog" aria-modal="true" onclick="if(event.target===this) this.classList.remove('open')">
    <div class="mobile-sheet-panel">
        <div style="width:36px;height:4px;border-radius:2px;background:#e2e8f0;margin:0 auto 14px;"></div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">Import Items from Excel</h2>
            <button onclick="document.getElementById('import-sheet').classList.remove('open')" aria-label="Close"
                    style="width:30px;height:30px;border-radius:8px;border:none;background:#f1f5f9;color:#64748b;font-size:16px;">×</button>
        </div>

        <form action="{{ route('inventory.items.import') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <label for="import-file-mobile" id="drop-zone-mobile"
                   style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;width:100%;box-sizing:border-box;border:2px dashed #cbd5e1;border-radius:14px;padding:28px 16px;cursor:pointer;">
                <svg width="32" height="32" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6M12 10v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2h-5.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0010.586 3H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <div style="text-align:center;">
                    <p id="item-drop-label-mobile" style="font-size:13px;font-weight:600;color:#334155;margin:0;">Tap to choose a file</p>
                    <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">Accepts: .xlsx, .xls — max 10 MB</p>
                </div>
                <input id="import-file-mobile" type="file" name="file" accept=".xlsx,.xls" style="position:absolute;width:1px;height:1px;opacity:0;"
                       onchange="document.getElementById('item-drop-label-mobile').textContent = this.files.length ? this.files[0].name : 'Tap to choose a file';">
            </label>

            <div style="margin-top:14px;padding:10px 12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;font-size:12px;color:#92400e;">
                Supports the <strong>Forkoll inventory format</strong> and the <strong>standard item template</strong>. Duplicate names are skipped.
            </div>

            <button type="submit" style="width:100%;margin-top:16px;padding:12px;border:none;border-radius:10px;font-size:14px;font-weight:700;color:#fff;background:#2563eb;">
                Import
            </button>
        </form>
    </div>
</div>

<style>
.mobile-sheet {
    display:none; position:fixed; inset:0; z-index:60; background:rgba(15,23,42,.5);
    align-items:flex-end; justify-content:center;
}
.mobile-sheet.open { display:flex; }
.mobile-sheet-panel {
    background:#fff; width:100%; max-width:520px; border-radius:20px 20px 0 0;
    padding:16px 18px calc(20px + env(safe-area-inset-bottom));
    box-shadow:0 -8px 30px rgba(0,0,0,.15);
    animation:sheetUp .2s ease;
    max-height:85vh; overflow-y:auto; box-sizing:border-box;
}
@keyframes sheetUp {
    from { transform:translateY(24px); opacity:0; }
    to   { transform:translateY(0); opacity:1; }
}
.hidden-by-search { display:none !important; }
</style>

<script>
// ---- Client-side search (CLAUDE.md gotcha #6 — instant, no reload, no ?search=) ----
(function () {
    var searchInput = document.getElementById('item-search');
    var rows        = document.querySelectorAll('.data-row');
    var countEl     = document.getElementById('item-search-count');
    var noResultsEl = document.getElementById('item-no-results');
    var total       = rows.length;

    searchInput.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
            row.classList.toggle('hidden-by-search', !match);
            if (match) visible++;
        });
        countEl.style.display = q ? 'block' : 'none';
        countEl.textContent = q ? (visible + ' of ' + total) : '';
        noResultsEl.style.display = (q && visible === 0) ? 'block' : 'none';
    });
}());

// ---- Per-card action menu (replaces separate Edit/Delete buttons) ----
function toggleItemMenu(id, event) {
    event.stopPropagation();
    document.querySelectorAll('.item-menu').forEach(function (menu) {
        if (menu.id !== 'item-menu-' + id) menu.style.display = 'none';
    });
    var target = document.getElementById('item-menu-' + id);
    target.style.display = target.style.display === 'block' ? 'none' : 'block';
}
function closeAllItemMenus() {
    document.querySelectorAll('.item-menu').forEach(function (menu) { menu.style.display = 'none'; });
}
document.addEventListener('click', closeAllItemMenus);
</script>
@endsection
