@extends('layouts.app')

@section('title', 'Record Customer Receipt')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Record Customer Receipt</h1>
    <p class="page-subtitle"><a href="{{ route('sales.payments.index') }}" class="text-blue-600 hover:underline">Receipts</a> / New</p>
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
    <form action="{{ route('sales.payments.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div class="sm:col-span-2">
                <label class="form-label">Invoice <span class="text-red-500">*</span></label>
                <select name="sales_invoice_id" required class="form-select">
                    <option value="">-- Select Unpaid Invoice --</option>
                    @foreach($unpaidInvoices as $invoice)
                        <option value="{{ $invoice->id }}" {{ (old('sales_invoice_id', request('invoice_id'))) == $invoice->id ? 'selected' : '' }}>
                            {{ $invoice->invoice_number }} - {{ $invoice->customer->name ?? '' }}
                            (Outstanding: {{ number_format($invoice->total_amount - $invoice->paid_amount, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Receipt Date <span class="text-red-500">*</span></label>
                <input type="date" name="receipt_date" value="{{ old('receipt_date', date('Y-m-d')) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Amount <span class="text-red-500">*</span></label>
                <input type="number" name="amount" value="{{ old('amount') }}" min="0.01" step="0.01" required class="form-input">
            </div>

            <div>
                <label class="form-label">Payment Method <span class="text-red-500">*</span></label>
                <select name="payment_method" required class="form-select">
                    <option value="">-- Select Method --</option>
                    <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="cheque" {{ old('payment_method') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                    <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div>
                <label class="form-label">Reference Number</label>
                <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-textarea">{{ old('notes') }}</textarea>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Record Receipt</button>
            <a href="{{ route('sales.payments.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
