@extends('layouts.app')

@section('title', 'Edit Sales Invoice')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Sales Invoice</h1>
    <p class="page-subtitle"><a href="{{ route('sales.invoices.index') }}" class="text-blue-600 hover:underline">Sales Invoices</a> / Edit</p>
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
    <form action="{{ route('sales.invoices.update', $salesInvoice) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Invoice Date <span class="text-red-500">*</span></label>
                <input type="date" name="invoice_date" value="{{ old('invoice_date', $salesInvoice->invoice_date ? \Carbon\Carbon::parse($salesInvoice->invoice_date)->format('Y-m-d') : '') }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" value="{{ old('due_date', $salesInvoice->due_date ? \Carbon\Carbon::parse($salesInvoice->due_date)->format('Y-m-d') : '') }}" class="form-input">
            </div>

            <div>
                <label class="form-label">Subtotal</label>
                <input type="number" name="subtotal" id="edit-subtotal" value="{{ old('subtotal', $salesInvoice->subtotal) }}"
                       min="0" step="0.01" onchange="calcEditTotal()" class="form-input">
            </div>

            <div>
                <label class="form-label">VAT Amount</label>
                <input type="number" name="vat_amount" id="edit-vat" value="{{ old('vat_amount', $salesInvoice->vat_amount) }}"
                       min="0" step="0.01" onchange="calcEditTotal()" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Total Amount</label>
                <input type="number" name="total_amount" id="edit-total" value="{{ old('total_amount', $salesInvoice->total_amount) }}"
                       readonly
                       class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm font-semibold text-gray-800">
            </div>

            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="unpaid" {{ old('status', $salesInvoice->status) === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                    <option value="partial" {{ old('status', $salesInvoice->status) === 'partial' ? 'selected' : '' }}>Partial</option>
                    <option value="paid" {{ old('status', $salesInvoice->status) === 'paid' ? 'selected' : '' }}>Paid</option>
                </select>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update Invoice</button>
            <a href="{{ route('sales.invoices.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
function calcEditTotal() {
    var sub = parseFloat(document.getElementById('edit-subtotal').value) || 0;
    var vat = parseFloat(document.getElementById('edit-vat').value) || 0;
    document.getElementById('edit-total').value = (sub + vat).toFixed(2);
}
</script>
@endsection
