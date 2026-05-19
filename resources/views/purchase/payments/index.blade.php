@extends('layouts.app')

@section('title', 'Supplier Payments')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Supplier Payments</h1>
        <p class="page-subtitle">Payment history to suppliers</p>
    </div>
    <a href="{{ route('purchase.payments.create') }}" class="btn-primary">
        + Record Payment
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Supplier</th>
                <th>Payment Date</th>
                <th class="text-right">Amount</th>
                <th>Method</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
            <tr>
                <td class="font-mono text-gray-700">{{ $payment->supplierInvoice->invoice_number ?? '-' }}</td>
                <td class="text-gray-800">{{ $payment->supplierInvoice->supplier->name ?? '' }}</td>
                <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '' }}</td>
                <td class="text-right font-medium text-gray-800">{{ number_format($payment->amount, 2) }}</td>
                <td class="capitalize">{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                <td>{{ $payment->reference_number ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No payments recorded.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($payments->hasPages())
<div class="mt-4">{{ $payments->links() }}</div>
@endif
@endsection
