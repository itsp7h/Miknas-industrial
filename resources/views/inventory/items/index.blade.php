@extends('layouts.app')

@section('title', 'Inventory Items')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Inventory Items</h1>
        <p class="page-subtitle">Manage all stock items</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('inventory.items.export-pdf') }}" class="btn btn-secondary" title="Export items as PDF">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6M9 17h4"/>
            </svg>
            Export PDF
        </a>
        <a href="{{ route('inventory.items.template') }}" class="btn btn-secondary" title="Download Excel import template">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Template
        </a>
        <button onclick="document.getElementById('import-modal').classList.remove('hidden')"
                class="btn btn-success" title="Import items from Excel">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Import Excel
        </button>
        <a href="{{ route('inventory.items.create') }}" class="btn-primary">
            + Add Item
        </a>
    </div>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Category</th>
                <th>UOM</th>
                <th class="text-right">Min Stock</th>
                <th class="text-right">Cost Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr>
                <td class="font-mono text-gray-700">{{ $item->item_code }}</td>
                <td class="font-medium text-gray-800">{{ $item->item_name }}</td>
                <td>
                    @php
                        $catBadgeClass = match($item->category) {
                            'raw_material'  => 'badge-blue',
                            'wip'           => 'badge-yellow',
                            'finished_good' => 'badge-green',
                            default         => 'badge-gray',
                        };
                        $catLabels = ['raw_material' => 'Raw Material', 'wip' => 'WIP', 'finished_good' => 'Finished Good'];
                    @endphp
                    <span class="{{ $catBadgeClass }}">
                        {{ $catLabels[$item->category] ?? ucfirst($item->category) }}
                    </span>
                </td>
                <td>{{ $item->unit_of_measure }}</td>
                <td class="text-right">{{ number_format($item->minimum_stock_level, 2) }}</td>
                <td class="text-right text-gray-800">{{ number_format($item->cost_price, 2) }}</td>
                <td>
                    @if($item->is_active)
                        <span class="badge-green">Active</span>
                    @else
                        <span class="badge-gray">Inactive</span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('inventory.items.edit', $item) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('inventory.items.destroy', $item) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this item?','This inventory item will be permanently removed.'); return false;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400">No items found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($items->hasPages())
<div class="mt-4">{{ $items->links() }}</div>
@endif

{{-- ═══════════ Import Modal ═══════════ --}}
<div id="import-modal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center"
     style="background:rgba(0,0,0,.45);">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4" style="animation:fadeInUp .2s ease;">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-800">Import Items from Excel</h2>
            <button onclick="document.getElementById('import-modal').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form action="{{ route('inventory.items.import') }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf

            <label for="import-file"
                   id="drop-zone"
                   class="flex flex-col items-center justify-center gap-3 w-full border-2 border-dashed border-slate-300 rounded-xl p-8 cursor-pointer transition-colors hover:border-blue-400 hover:bg-blue-50"
                   ondragover="event.preventDefault(); this.classList.add('border-blue-500','bg-blue-50')"
                   ondragleave="this.classList.remove('border-blue-500','bg-blue-50')"
                   ondrop="itemHandleDrop(event)">
                <svg width="36" height="36" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 13h6M12 10v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2h-5.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0010.586 3H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <div class="text-center">
                    <p id="item-drop-label" class="text-sm font-medium text-slate-700">
                        Click to choose or drag & drop your file
                    </p>
                    <p class="text-xs text-slate-400 mt-1">Accepts: .xlsx, .xls — max 10 MB</p>
                </div>
                <input id="import-file" type="file" name="file" accept=".xlsx,.xls"
                       class="sr-only" onchange="itemUpdateLabel(this)">
            </label>

            <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-700 flex gap-2">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>
                    Supports the <strong>Forkoll inventory format</strong> and the
                    <strong>standard item template</strong>. Categories are auto-detected.
                    Items marked "Not in Use" are imported as inactive. Duplicate names are skipped.
                </span>
            </div>

            <div class="flex items-center justify-end gap-3 mt-5">
                <button type="button"
                        onclick="document.getElementById('import-modal').classList.add('hidden')"
                        class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes fadeInUp {
    from { opacity:0; transform:translateY(12px); }
    to   { opacity:1; transform:translateY(0); }
}
</style>

<script>
function itemUpdateLabel(input) {
    document.getElementById('item-drop-label').textContent =
        input.files.length ? input.files[0].name : 'Click to choose or drag & drop your file';
}
function itemHandleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('border-blue-500', 'bg-blue-50');
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const input = document.getElementById('import-file');
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    itemUpdateLabel(input);
}
document.getElementById('import-modal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
@endsection
