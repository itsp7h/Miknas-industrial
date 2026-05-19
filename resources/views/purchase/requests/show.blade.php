@extends('layouts.app')

@section('title', 'MPR ' . $purchaseRequest->request_number)

@section('content')
<div class="mb-6 flex items-start justify-between flex-wrap gap-3">
    <div>
        <h1 class="page-title">{{ $purchaseRequest->request_number }}</h1>
        <p class="page-subtitle"><a href="{{ route('purchase.requests.index') }}" class="text-blue-600 hover:underline">Purchase Requests</a> / {{ $purchaseRequest->request_number }}</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('purchase.requests.print', $purchaseRequest) }}" target="_blank"
           class="btn-primary">Print MPR Form</a>
        @if($purchaseRequest->status === 'pending')
            <a href="{{ route('purchase.requests.edit', $purchaseRequest) }}" class="btn-secondary">Edit</a>
            <form action="{{ route('purchase.requests.approve', $purchaseRequest) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit" class="btn-success">Approve</button>
            </form>
            <form action="{{ route('purchase.requests.reject', $purchaseRequest) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit" class="btn-danger">Reject</button>
            </form>
        @endif
    </div>
</div>

{{-- Status Badge --}}
<div class="mb-4">
    @php
        $badgeClass = match($purchaseRequest->status) {
            'pending'  => 'badge-yellow',
            'approved' => 'badge-green',
            'rejected' => 'badge-red',
            'ordered'  => 'badge-blue',
            default    => 'badge-gray',
        };
    @endphp
    <span class="text-sm font-medium">Status:</span>
    <span class="{{ $badgeClass }} ml-1">{{ ucfirst($purchaseRequest->status) }}</span>
</div>

{{-- Header Info --}}
<div class="card card-body mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Project / Department Details</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
        <div>
            <p class="text-gray-500">MPR Number</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->request_number }}</p>
        </div>
        <div>
            <p class="text-gray-500">Date</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->date->format('d-m-Y') }}</p>
        </div>
        <div>
            <p class="text-gray-500">Project / Site Name</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->project_name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Requested By</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->requested_by_name ?? $purchaseRequest->requestedBy?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Required Date</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->required_date_text ?? '—' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Location / Site</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->location ?? '—' }}</p>
        </div>
        @if($purchaseRequest->department)
        <div>
            <p class="text-gray-500">Department</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->department }}</p>
        </div>
        @endif
        @if($purchaseRequest->remarks)
        <div class="col-span-2 sm:col-span-3">
            <p class="text-gray-500">Remarks</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->remarks }}</p>
        </div>
        @endif
    </div>
</div>

{{-- Material Items --}}
<div class="card card-body mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Material Details</h2>
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th class="w-12">S.No</th>
                    <th>Description of Material</th>
                    <th>Unit</th>
                    <th>Qty Required</th>
                    <th>Purpose / Use</th>
                    <th>Required Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseRequest->items as $i => $item)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="font-medium">{{ $item->description }}</td>
                    <td>{{ $item->unit ?? '—' }}</td>
                    <td>{{ number_format($item->quantity_required, 2) }}</td>
                    <td>{{ $item->purpose_use ?? '—' }}</td>
                    <td>{{ $item->required_date ? $item->required_date->format('d-m-Y') : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Approval Info --}}
@if($purchaseRequest->approvedBy)
<div class="card card-body">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Approval Info</h2>
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <p class="text-gray-500">Approved By</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->approvedBy->name }}</p>
        </div>
        <div>
            <p class="text-gray-500">Approved At</p>
            <p class="font-semibold text-gray-800">{{ $purchaseRequest->approved_at?->format('d M Y, H:i') ?? '—' }}</p>
        </div>
    </div>
</div>
@endif
@endsection
