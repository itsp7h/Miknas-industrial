@extends('layouts.app')

@section('title', 'Purchase Requests')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Purchase Requests</h1>
        <p class="page-subtitle">Manage internal purchase requests</p>
    </div>
    <x-purchase.request-modal />
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Request #</th>
                <th>Date</th>
                <th>Department</th>
                <th>First Item</th>
                <th>Qty / Items</th>
                <th>Status</th>
                <th>Requested By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $request)
            <tr>
                <td class="font-mono text-gray-700">
                    <a href="{{ route('purchase.requests.show', $request) }}" class="text-blue-600 hover:underline">{{ $request->request_number ?? '#' . $request->id }}</a>
                </td>
                <td>{{ $request->date ? $request->date->format('d M Y') : '' }}</td>
                <td>{{ $request->department }}</td>
                <td class="text-gray-800">{{ $request->items->first()->description ?? '—' }}</td>
                <td>{{ $request->items->count() > 1 ? $request->items->count() . ' items' : ($request->items->first() ? number_format($request->items->first()->quantity_required, 2) . ' ' . $request->items->first()->unit : '—') }}</td>
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
                        <x-purchase.edit-request-modal :purchaseRequest="$request" />
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
