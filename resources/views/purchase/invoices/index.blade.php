@extends('layouts.app')

@section('title', 'Supplier Invoices')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Supplier Invoices</h1>
        <p class="page-subtitle">Track and manage supplier invoices</p>
    </div>
    <a href="{{ route('purchase.invoices.create') }}" class="btn-primary">
        + New Invoice
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Supplier</th>
                <th>PO #</th>
                <th>Date</th>
                <th class="text-right">Total</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Outstanding</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $invoice)
            @php $outstanding = $invoice->total_amount - $invoice->paid_amount; @endphp
            <tr>
                <td class="font-mono text-gray-700">{{ $invoice->invoice_number }}</td>
                <td class="text-gray-800">{{ $invoice->supplier->name ?? '' }}</td>
                <td class="font-mono text-gray-600">{{ $invoice->purchaseOrder->po_number ?? '-' }}</td>
                <td>{{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') : '' }}</td>
                <td class="text-right text-gray-800">{{ number_format($invoice->total_amount, 2) }}</td>
                <td class="text-right text-green-700">{{ number_format($invoice->paid_amount, 2) }}</td>
                <td class="text-right {{ $outstanding > 0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ number_format($outstanding, 2) }}</td>
                <td>
                    @php
                        $badgeClass = match($invoice->status ?? 'unpaid') {
                            'unpaid' => 'badge-red',
                            'partial' => 'badge-yellow',
                            'paid' => 'badge-green',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucfirst($invoice->status ?? 'unpaid') }}</span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('purchase.payments.create', ['invoice_id' => $invoice->id]) }}" class="btn-success btn-sm">Pay</a>
                        <a href="{{ route('purchase.invoices.edit', $invoice) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('purchase.invoices.destroy', $invoice) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this invoice?','This invoice will be permanently removed.'); return false;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="px-4 py-8 text-center text-gray-400">No invoices found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($invoices->hasPages())
<div class="mt-4">{{ $invoices->links() }}</div>
@endif
@endsection
