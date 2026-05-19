@extends('layouts.app')

@section('title', 'Warehouses')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Warehouses</h1>
        <p class="page-subtitle">Manage storage locations</p>
    </div>
    <a href="{{ route('inventory.warehouses.create') }}" class="btn-primary">
        + Add Warehouse
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Location</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($warehouses as $warehouse)
            <tr>
                <td class="font-mono text-gray-700">{{ $warehouse->code }}</td>
                <td class="font-medium text-gray-800">{{ $warehouse->name }}</td>
                <td>{{ $warehouse->location }}</td>
                <td>
                    @if($warehouse->is_active)
                        <span class="badge-green">Active</span>
                    @else
                        <span class="badge-gray">Inactive</span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('inventory.warehouses.edit', $warehouse) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('inventory.warehouses.destroy', $warehouse) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this warehouse?','This warehouse will be permanently removed.'); return false;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-400">No warehouses found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($warehouses->hasPages())
<div class="mt-4">{{ $warehouses->links() }}</div>
@endif
@endsection
