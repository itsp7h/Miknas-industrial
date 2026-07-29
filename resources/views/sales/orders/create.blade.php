@extends('layouts.app')

@section('title', 'New Sales Order')

@section('content')
<div class="mb-6">
    <h1 class="page-title">New Sales Order</h1>
    <p class="page-subtitle"><a href="{{ route('sales.orders.index') }}" class="text-blue-600 hover:underline">Sales Orders</a> / New</p>
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

<form action="{{ route('sales.orders.store') }}" method="POST">
    @csrf

    <!-- Header -->
    <div class="card card-body mb-4">
        <h2 class="text-base font-semibold text-gray-700 mb-4">Order Details</h2>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

            <div>
                <label class="form-label">Customer <span class="text-red-500">*</span></label>
                <select name="customer_id" required class="form-select">
                    <option value="">-- Select Customer --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Order Date <span class="text-red-500">*</span></label>
                <input type="date" name="order_date" value="{{ old('order_date', date('Y-m-d')) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Delivery Date</label>
                <input type="date" name="delivery_date" value="{{ old('delivery_date') }}" class="form-input">
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-textarea">{{ old('notes') }}</textarea>
            </div>

        </div>
    </div>

    <!-- Items -->
    <div class="card card-body mb-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-700">Order Items</h2>
            <button type="button" onclick="addSORow()" class="btn-primary btn-sm">+ Add Row</button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="pb-2 text-left font-semibold text-gray-600">Product</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">Quantity</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">Unit Price</th>
                        <th class="pb-2 text-left font-semibold text-gray-600 w-28">Total</th>
                        <th class="pb-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="so-items-body">
                    <tr class="so-item-row">
                        <td class="py-1 pr-2">
                            <select name="items[0][item_id]"
                                    class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
                                <option value="">-- Select Product --</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->item_code }} - {{ $item->item_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1 pr-2">
                            <input type="number" name="items[0][quantity]" placeholder="0" min="0" step="0.01"
                                   onchange="calcSORowTotal(this)"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm so-qty">
                        </td>
                        <td class="py-1 pr-2">
                            <input type="number" name="items[0][price]" placeholder="0.00" min="0" step="0.01"
                                   onchange="calcSORowTotal(this)"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm so-price">
                        </td>
                        <td class="py-1 pr-2">
                            <input type="number" name="items[0][total]" placeholder="0.00" readonly
                                   class="w-full border border-gray-200 bg-gray-50 rounded px-2 py-1.5 text-sm so-total">
                        </td>
                        <td class="py-1">
                            <button type="button" onclick="removeSORow(this)" class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex justify-end">
            <div class="text-sm font-semibold text-gray-700">
                Grand Total: <span id="so-grand-total" class="text-blue-700">0.00</span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">Create Sales Order</button>
        <a href="{{ route('sales.orders.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>

<script>
var soRowIndex = 1;
var soItemsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->item_code }} - {{ $item->item_name }}</option>@endforeach`;

function addSORow() {
    var tbody = document.getElementById('so-items-body');
    var row = document.createElement('tr');
    row.className = 'so-item-row';
    row.innerHTML = `
        <td class="py-1 pr-2">
            <select name="items[${soRowIndex}][item_id]" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm">
                <option value="">-- Select Product --</option>
                ${soItemsHtml}
            </select>
        </td>
        <td class="py-1 pr-2">
            <input type="number" name="items[${soRowIndex}][quantity]" placeholder="0" min="0" step="0.01"
                   onchange="calcSORowTotal(this)" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm so-qty">
        </td>
        <td class="py-1 pr-2">
            <input type="number" name="items[${soRowIndex}][price]" placeholder="0.00" min="0" step="0.01"
                   onchange="calcSORowTotal(this)" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm so-price">
        </td>
        <td class="py-1 pr-2">
            <input type="number" name="items[${soRowIndex}][total]" placeholder="0.00" readonly
                   class="w-full border border-gray-200 bg-gray-50 rounded px-2 py-1.5 text-sm so-total">
        </td>
        <td class="py-1">
            <button type="button" onclick="removeSORow(this)" class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
        </td>`;
    tbody.appendChild(row);
    soRowIndex++;
}

function removeSORow(btn) {
    var rows = document.querySelectorAll('.so-item-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        updateSOGrandTotal();
    }
}

function calcSORowTotal(input) {
    var row = input.closest('tr');
    var qty = parseFloat(row.querySelector('.so-qty').value) || 0;
    var price = parseFloat(row.querySelector('.so-price').value) || 0;
    row.querySelector('.so-total').value = (qty * price).toFixed(2);
    updateSOGrandTotal();
}

function updateSOGrandTotal() {
    var totals = document.querySelectorAll('.so-total');
    var grand = 0;
    totals.forEach(function(t) { grand += parseFloat(t.value) || 0; });
    document.getElementById('so-grand-total').textContent = grand.toFixed(2);
}
</script>
@endsection
