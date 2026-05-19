@extends('layouts.app')

@section('title', 'Customer Receipts')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Customer Receipts</h1>
        <p class="page-subtitle">Payment receipts from customers</p>
    </div>
    <a href="{{ route('sales.payments.create') }}" class="btn-primary">
        + Record Receipt
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Invoice #</th>
                <th>Date</th>
                <th class="text-right">Amount</th>
                <th>Method</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
            <tr>
                <td class="text-gray-800">{{ $payment->salesInvoice->customer->name ?? '' }}</td>
                <td class="font-mono text-gray-700">{{ $payment->salesInvoice->invoice_number ?? '-' }}</td>
                <td class="text-gray-600">{{ $payment->receipt_date ? \Carbon\Carbon::parse($payment->receipt_date)->format('d M Y') : '' }}</td>
                <td class="text-right font-medium text-gray-800">{{ number_format($payment->amount, 2) }}</td>
                <td class="text-gray-600 capitalize">{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                <td class="text-gray-600">{{ $payment->reference_number ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No receipts recorded.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($payments->hasPages())
<div class="mt-4">{{ $payments->links() }}</div>
@endif
@endsection
