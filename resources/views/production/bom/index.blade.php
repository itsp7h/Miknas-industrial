@extends('layouts.app')

@section('title', 'Bill of Materials')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Bill of Materials</h1>
        <p class="page-subtitle">Define material requirements for each product</p>
    </div>
    <a href="{{ route('production.bom.create') }}" class="btn-primary">
        + Add BOM Entry
    </a>
</div>

@php $currentProduct = null; @endphp
@forelse($bomEntries as $entry)
    @if($currentProduct !== ($entry->product->item_name ?? ''))
        @if($currentProduct !== null)
            </tbody></table></div>
        @endif
        @php $currentProduct = $entry->product->item_name ?? ''; @endphp
        <div class="table-wrapper overflow-hidden mb-4">
            <div class="px-6 py-3 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
                <h3 class="font-semibold text-blue-800 text-sm">{{ $currentProduct }}</h3>
                <span class="text-xs text-blue-500">{{ $entry->product->item_code ?? '' }}</span>
            </div>
            <table class="table-base">
                <thead>
                    <tr>
                        <th>Raw Material</th>
                        <th class="text-right">Qty Required</th>
                        <th>UOM</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    @endif
                    <tr>
                        <td class="text-gray-800">{{ $entry->rawMaterial->item_name ?? '' }}</td>
                        <td class="text-right text-gray-700">{{ number_format($entry->quantity_required, 2) }}</td>
                        <td class="text-gray-500">{{ $entry->unit_of_measure }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('production.bom.edit', $entry) }}" class="btn-secondary btn-sm">Edit</a>
                                <form action="{{ route('production.bom.destroy', $entry) }}" method="POST"
                                      onsubmit="confirmDelete(this,'Delete this BOM entry?','This bill of materials entry will be permanently removed.'); return false;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
@empty
    <div class="card card-body text-center text-gray-400">
        No BOM entries found. <a href="{{ route('production.bom.create') }}" class="text-blue-600 hover:underline">Add the first one</a>.
    </div>
@endforelse
@if($currentProduct !== null && $bomEntries->count())
                </tbody>
            </table>
        </div>
@endif
@endsection
