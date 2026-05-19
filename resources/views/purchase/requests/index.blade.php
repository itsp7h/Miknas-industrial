@extends('layouts.app')

@section('title', 'Purchase Requests')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Purchase Requests</h1>
        <p class="page-subtitle">Manage internal purchase requests</p>
    </div>
    <a href="{{ route('purchase.requests.create') }}" class="btn-primary">
        + New Request
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Request #</th>
                <th>Date</th>
                <th>Department</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Requested By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $request)
            <tr>
                <td class="font-mono text-gray-700">{{ $request->request_number ?? '#' . $request->id }}</td>
                <td>{{ $request->date ? $request->date->format('d M Y') : '' }}</td>
                <td>{{ $request->department }}</td>
                <td class="text-gray-800">{{ $request->item->item_name ?? $request->item_name }}</td>
                <td>{{ $request->quantity }} {{ $request->unit_of_measure }}</td>
                <td>
                    @php
                        $badgeClass = match($request->status) {
                            'pending' => 'badge-yellow',
                            'approved' => 'badge-green',
                            'rejected' => 'badge-red',
                            'ordered' => 'badge-blue',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucfirst($request->status) }}</span>
                </td>
                <td>{{ $request->requestedBy->name ?? $request->requested_by }}</td>
                <td>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($request->status === 'pending')
                            <form action="{{ route('purchase.requests.approve', $request) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-success btn-sm">Approve</button>
                            </form>
                            <form action="{{ route('purchase.requests.reject', $request) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-danger btn-sm">Reject</button>
                            </form>
                        @endif
                        <a href="{{ route('purchase.requests.edit', $request) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('purchase.requests.destroy', $request) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this purchase request?','This request will be permanently removed.'); return false;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400">No purchase requests found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($requests->hasPages())
<div class="mt-4">{{ $requests->links() }}</div>
@endif
@endsection
