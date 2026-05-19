@extends('layouts.app')

@section('title', 'New Purchase Order')

@section('content')
<div class="mb-6">
    <h1 class="page-title">New Purchase Order</h1>
    <p class="page-subtitle"><a href="{{ route('purchase.orders.index') }}" class="text-blue-600 hover:underline">Purchase Orders</a> / New</p>
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

<form action="{{ route('purchase.orders.store') }}" method="POST">
    @csrf

    <!-- Header Section -->
    <div class="card card-body mb-4">
        <h2 class="text-base font-semibold text-gray-700 mb-4">Order Details</h2>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

            <div>
                <label class="form-label">Supplier <span class="text-red-500">*</span></label>
                <select name="supplier_id" required class="form-select">
                    <option value="">-- Select Supplier --</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Purchase Request (optional)</label>
                <select name="purchase_request_id" class="form-select">
                    <option value="">-- None --</option>
                    @foreach($purchaseRequests as $pr)
                        <option value="{{ $pr->id }}" {{ old('purchase_request_id') == $pr->id ? 'selected' : '' }}>
                            {{ $pr->request_number ?? '#' . $pr->id }} - {{ $pr->item->item_name ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">PO Date <span class="text-red-500">*</span></label>
                <input type="date" name="po_date" value="{{ old('po_date', date('Y-m-d')) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Expected Delivery Date</label>
                <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date') }}" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-textarea">{{ old('notes') }}</textarea>
            </div>

        </div>
    </div>

    <!-- Items Section -->
    <div class="card card-body mb-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-700">Order Items</h2>
            <button type="button" onclick="addPORow()" class="btn-primary btn-sm">+ Add Row</button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm" id="po-items-table">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="pb-2 text-left font-semibold text-gray-600">Item</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">Quantity</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">Rate</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">Total</th>
                        <th class="pb-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="po-items-body">
                    <tr class="po-item-row">
                        <td class="py-1 pr-2">
                            <select name="items[0][item_id]"
                                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
                                <option value="">-- Select Item --</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->item_code }} - {{ $item->item_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1 pr-2">
                            <input type="number" name="items[0][quantity]" placeholder="0"
                                   min="0" step="0.01"
                                   onchange="calcRowTotal(this)"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 qty-input">
                        </td>
                        <td class="py-1 pr-2">
                            <input type="number" name="items[0][rate]" placeholder="0.00"
                                   min="0" step="0.01"
                                   onchange="calcRowTotal(this)"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 rate-input">
                        </td>
                        <td class="py-1 pr-2">
                            <input type="number" name="items[0][total]" placeholder="0.00"
                                   readonly
                                   class="w-full border border-gray-200 bg-gray-50 rounded px-2 py-1.5 text-sm row-total">
                        </td>
                        <td class="py-1">
                            <button type="button" onclick="removePORow(this)" class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex justify-end">
            <div class="text-sm font-semibold text-gray-700">
                Grand Total: <span id="grand-total" class="text-blue-700">0.00</span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">Create Purchase Order</button>
        <a href="{{ route('purchase.orders.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>

<script>
var poRowIndex = 1;
var itemsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->item_code }} - {{ $item->item_name }}</option>@endforeach`;

function addPORow() {
    var tbody = document.getElementById('po-items-body');
    var row = document.createElement('tr');
    row.className = 'po-item-row';
    row.innerHTML = `
        <td class="py-1 pr-2">
            <select name="items[${poRowIndex}][item_id]" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
                <option value="">-- Select Item --</option>
                ${itemsHtml}
            </select>
        </td>
        <td class="py-1 pr-2">
            <input type="number" name="items[${poRowIndex}][quantity]" placeholder="0" min="0" step="0.01"
                   onchange="calcRowTotal(this)" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm qty-input">
        </td>
        <td class="py-1 pr-2">
            <input type="number" name="items[${poRowIndex}][rate]" placeholder="0.00" min="0" step="0.01"
                   onchange="calcRowTotal(this)" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm rate-input">
        </td>
        <td class="py-1 pr-2">
            <input type="number" name="items[${poRowIndex}][total]" placeholder="0.00" readonly
                   class="w-full border border-gray-200 bg-gray-50 rounded px-2 py-1.5 text-sm row-total">
        </td>
        <td class="py-1">
            <button type="button" onclick="removePORow(this)" class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
        </td>`;
    tbody.appendChild(row);
    poRowIndex++;
}

function removePORow(btn) {
    var rows = document.querySelectorAll('.po-item-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        updateGrandTotal();
    }
}

function calcRowTotal(input) {
    var row = input.closest('tr');
    var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    var rate = parseFloat(row.querySelector('.rate-input').value) || 0;
    var total = qty * rate;
    row.querySelector('.row-total').value = total.toFixed(2);
    updateGrandTotal();
}

function updateGrandTotal() {
    var totals = document.querySelectorAll('.row-total');
    var grand = 0;
    totals.forEach(function(t) { grand += parseFloat(t.value) || 0; });
    document.getElementById('grand-total').textContent = grand.toFixed(2);
}
</script>
@endsection
