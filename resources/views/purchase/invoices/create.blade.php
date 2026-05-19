@extends('layouts.app')

@section('title', 'New Supplier Invoice')

@section('content')
<div class="mb-6">
    <h1 class="page-title">New Supplier Invoice</h1>
    <p class="page-subtitle"><a href="{{ route('purchase.invoices.index') }}" class="text-blue-600 hover:underline">Supplier Invoices</a> / New</p>
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
    <form action="{{ route('purchase.invoices.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Invoice Number <span class="text-red-500">*</span></label>
                <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" required class="form-input">
            </div>

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
                <label class="form-label">Purchase Order</label>
                <select name="purchase_order_id" class="form-select">
                    <option value="">-- None --</option>
                    @foreach($purchaseOrders as $po)
                        <option value="{{ $po->id }}" {{ old('purchase_order_id') == $po->id ? 'selected' : '' }}>
                            {{ $po->po_number ?? 'PO-' . str_pad($po->id, 5, '0', STR_PAD_LEFT) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">GRN</label>
                <select name="goods_receipt_note_id" class="form-select">
                    <option value="">-- None --</option>
                    @foreach($grns as $grn)
                        <option value="{{ $grn->id }}" {{ old('goods_receipt_note_id') == $grn->id ? 'selected' : '' }}>
                            {{ $grn->grn_number ?? 'GRN-' . str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}
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
                <input type="number" name="subtotal" id="subtotal" value="{{ old('subtotal') }}"
                       min="0" step="0.01" required onchange="calcInvoiceTotal()" class="form-input">
            </div>

            <div>
                <label class="form-label">VAT Amount</label>
                <input type="number" name="vat_amount" id="vat_amount" value="{{ old('vat_amount', 0) }}"
                       min="0" step="0.01" onchange="calcInvoiceTotal()" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Total Amount</label>
                <input type="number" name="total_amount" id="total_amount" value="{{ old('total_amount') }}"
                       readonly
                       class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm font-semibold text-gray-800">
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Save Invoice</button>
            <a href="{{ route('purchase.invoices.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
function calcInvoiceTotal() {
    var subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    var vat = parseFloat(document.getElementById('vat_amount').value) || 0;
    document.getElementById('total_amount').value = (subtotal + vat).toFixed(2);
}
</script>
@endsection
