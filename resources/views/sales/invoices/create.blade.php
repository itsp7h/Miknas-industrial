@extends('layouts.app')

@section('title', 'New Sales Invoice')

@section('content')
<div class="mb-6">
    <h1 class="page-title">New Sales Invoice</h1>
    <p class="page-subtitle"><a href="{{ route('sales.invoices.index') }}" class="text-blue-600 hover:underline">Sales Invoices</a> / New</p>
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

<div class="card card-body max-w-2xl">
    <form action="{{ route('sales.invoices.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Sales Order <span class="text-red-500">*</span></label>
                <select name="sales_order_id" id="inv-so-select" required onchange="fillCustomer(this)" class="form-select">
                    <option value="">-- Select Dispatched Order --</option>
                    @foreach($salesOrders as $so)
                        <option value="{{ $so->id }}"
                                data-customer="{{ $so->customer_id }}"
                                data-total="{{ $so->total_amount }}"
                                {{ old('sales_order_id') == $so->id ? 'selected' : '' }}>
                            {{ $so->order_number ?? 'SO-' . str_pad($so->id, 5, '0', STR_PAD_LEFT) }} - {{ $so->customer->name ?? '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Customer</label>
                <select name="customer_id" id="inv-customer" class="form-select">
                    <option value="">-- Auto-filled from SO --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Invoice Date <span class="text-red-500">*</span></label>
                <input type="date" name="invoice_date" value="{{ old('invoice_date', date('Y-m-d')) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}" class="form-input">
            </div>

            <div>
                <label class="form-label">Subtotal <span class="text-red-500">*</span></label>
                <input type="number" name="subtotal" id="inv-subtotal" value="{{ old('subtotal') }}"
                       min="0" step="0.01" required onchange="calcSalesInvoiceTotal()" class="form-input">
            </div>

            <div>
                <label class="form-label">VAT Rate (%)</label>
                <input type="number" name="vat_rate" id="inv-vat-rate" value="{{ old('vat_rate', 0) }}"
                       min="0" max="100" step="0.01" onchange="calcSalesInvoiceTotal()" class="form-input">
            </div>

            <div>
                <label class="form-label">VAT Amount</label>
                <input type="number" name="vat_amount" id="inv-vat-amount" value="{{ old('vat_amount', 0) }}"
                       min="0" step="0.01" readonly
                       class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="form-label">Total Amount</label>
                <input type="number" name="total_amount" id="inv-total" value="{{ old('total_amount') }}"
                       readonly
                       class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm font-semibold text-gray-800">
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Save Invoice</button>
            <a href="{{ route('sales.invoices.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
function fillCustomer(select) {
    var option = select.options[select.selectedIndex];
    var customerId = option.dataset.customer;
    var total = option.dataset.total;
    var customerSelect = document.getElementById('inv-customer');

    // Auto-select customer
    for (var i = 0; i < customerSelect.options.length; i++) {
        if (customerSelect.options[i].value == customerId) {
            customerSelect.selectedIndex = i;
            break;
        }
    }

    // Auto-fill subtotal from SO
    if (total) {
        document.getElementById('inv-subtotal').value = parseFloat(total).toFixed(2);
        calcSalesInvoiceTotal();
    }
}

function calcSalesInvoiceTotal() {
    var subtotal = parseFloat(document.getElementById('inv-subtotal').value) || 0;
    var vatRate = parseFloat(document.getElementById('inv-vat-rate').value) || 0;
    var vatAmount = subtotal * vatRate / 100;
    document.getElementById('inv-vat-amount').value = vatAmount.toFixed(2);
    document.getElementById('inv-total').value = (subtotal + vatAmount).toFixed(2);
}
</script>
@endsection
