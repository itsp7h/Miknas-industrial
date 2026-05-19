@extends('layouts.app')

@section('title', 'Edit Purchase Request')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Purchase Request</h1>
    <p class="page-subtitle">
        <a href="{{ route('purchase.requests.index') }}" class="text-blue-600 hover:underline">Purchase Requests</a> /
        <a href="{{ route('purchase.pipeline.show', $purchaseRequest) }}" class="text-blue-600 hover:underline">{{ $purchaseRequest->request_number }}</a> /
        Edit
    </p>
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

<form action="{{ route('purchase.requests.update', $purchaseRequest) }}" method="POST" id="mpr-form">
    @csrf
    @method('PUT')

    {{-- Header Details --}}
    <div class="card card-body mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-4">Project / Department Details</h2>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

            <div>
                <label class="form-label">Date <span class="text-red-500">*</span></label>
                <input type="date" name="date"
                       value="{{ old('date', $purchaseRequest->date ? \Carbon\Carbon::parse($purchaseRequest->date)->format('Y-m-d') : '') }}"
                       required class="form-input">
            </div>

            <div>
                <label class="form-label">Project / Site Name <span class="text-red-500">*</span></label>
                <input type="text" name="project_name"
                       value="{{ old('project_name', $purchaseRequest->project_name) }}"
                       required class="form-input" placeholder="e.g. Steel Tech Co WLL">
            </div>

            <div>
                <label class="form-label">Requested By <span class="text-red-500">*</span></label>
                <input type="text" name="requested_by_name"
                       value="{{ old('requested_by_name', $purchaseRequest->requested_by_name) }}"
                       required class="form-input" placeholder="Person's name">
            </div>

            <div>
                <label class="form-label">Required Date / Urgency</label>
                <input type="text" name="required_date_text"
                       value="{{ old('required_date_text', $purchaseRequest->required_date_text) }}"
                       class="form-input" placeholder="e.g. Urgent, or 2026-06-01">
            </div>

            <div>
                <label class="form-label">Location / Site</label>
                <input type="text" name="location"
                       value="{{ old('location', $purchaseRequest->location) }}"
                       class="form-input" placeholder="e.g. BaFa - Hidd">
            </div>

            <div>
                <label class="form-label">Department</label>
                <input type="text" name="department"
                       value="{{ old('department', $purchaseRequest->department) }}"
                       class="form-input" placeholder="e.g. Operations, Production">
            </div>

        </div>
    </div>

    {{-- Material Items --}}
    <div class="card card-body mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-700">Material Details</h2>
            <button type="button" id="add-row" class="btn-primary btn-sm">+ Add Item</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse" id="items-table">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <th class="border border-gray-300 px-3 py-2 text-left w-10">S.No</th>
                        <th class="border border-gray-300 px-3 py-2 text-left">Description of Material <span class="text-red-500">*</span></th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-24">Unit</th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-28">Qty Required <span class="text-red-500">*</span></th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-40">Purpose / Use</th>
                        <th class="border border-gray-300 px-3 py-2 text-left w-36">Required Date</th>
                        <th class="border border-gray-300 px-2 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @php
                        $existingItems = old('items')
                            ? collect(old('items'))->map(fn($i) => (object)$i)
                            : $purchaseRequest->items;
                    @endphp
                    @foreach($existingItems as $idx => $item)
                    <tr class="item-row">
                        <td class="border border-gray-300 px-3 py-2 text-center text-gray-500 row-num">{{ $idx + 1 }}</td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="text" name="items[{{ $idx }}][description]"
                                   value="{{ is_array($item) ? ($item['description'] ?? '') : $item->description }}"
                                   class="w-full border-0 focus:ring-0 text-sm" placeholder="Material description" required>
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="text" name="items[{{ $idx }}][unit]"
                                   value="{{ is_array($item) ? ($item['unit'] ?? '') : $item->unit }}"
                                   class="w-full border-0 focus:ring-0 text-sm" placeholder="PCS, NOS, KG…">
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="number" name="items[{{ $idx }}][quantity_required]"
                                   value="{{ is_array($item) ? ($item['quantity_required'] ?? '') : $item->quantity_required }}"
                                   min="0.01" step="0.01" class="w-full border-0 focus:ring-0 text-sm" placeholder="0" required>
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="text" name="items[{{ $idx }}][purpose_use]"
                                   value="{{ is_array($item) ? ($item['purpose_use'] ?? '') : $item->purpose_use }}"
                                   class="w-full border-0 focus:ring-0 text-sm" placeholder="Purpose…">
                        </td>
                        <td class="border border-gray-300 px-2 py-1">
                            <input type="date" name="items[{{ $idx }}][required_date]"
                                   value="{{ is_array($item) ? ($item['required_date'] ?? '') : ($item->required_date ? \Carbon\Carbon::parse($item->required_date)->format('Y-m-d') : '') }}"
                                   class="w-full border-0 focus:ring-0 text-sm">
                        </td>
                        <td class="border border-gray-300 px-2 py-1 text-center">
                            <button type="button" class="remove-row text-red-500 hover:text-red-700 font-bold text-lg leading-none">×</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Remarks --}}
    <div class="card card-body mb-6">
        <label class="form-label">Remarks / Notes</label>
        <textarea name="remarks" rows="2" class="form-textarea">{{ old('remarks', $purchaseRequest->remarks) }}</textarea>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">Save Changes</button>
        <a href="{{ route('purchase.pipeline.show', $purchaseRequest) }}" class="btn-secondary">Cancel</a>
    </div>
</form>

<script>
(function () {
    let rowIndex = {{ $existingItems->count() }};

    function renumber() {
        document.querySelectorAll('#items-body .row-num').forEach((el, i) => {
            el.textContent = i + 1;
        });
    }

    function newRow() {
        const idx = rowIndex++;
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td class="border border-gray-300 px-3 py-2 text-center text-gray-500 row-num"></td>
            <td class="border border-gray-300 px-2 py-1">
                <input type="text" name="items[${idx}][description]" class="w-full border-0 focus:ring-0 text-sm" placeholder="Material description" required>
            </td>
            <td class="border border-gray-300 px-2 py-1">
                <input type="text" name="items[${idx}][unit]" class="w-full border-0 focus:ring-0 text-sm" placeholder="PCS, NOS, KG…">
            </td>
            <td class="border border-gray-300 px-2 py-1">
                <input type="number" name="items[${idx}][quantity_required]" min="0.01" step="0.01" class="w-full border-0 focus:ring-0 text-sm" placeholder="0" required>
            </td>
            <td class="border border-gray-300 px-2 py-1">
                <input type="text" name="items[${idx}][purpose_use]" class="w-full border-0 focus:ring-0 text-sm" placeholder="Purpose…">
            </td>
            <td class="border border-gray-300 px-2 py-1">
                <input type="date" name="items[${idx}][required_date]" class="w-full border-0 focus:ring-0 text-sm">
            </td>
            <td class="border border-gray-300 px-2 py-1 text-center">
                <button type="button" class="remove-row text-red-500 hover:text-red-700 font-bold text-lg leading-none">×</button>
            </td>
        `;
        return tr;
    }

    document.getElementById('add-row').addEventListener('click', function () {
        const tbody = document.getElementById('items-body');
        tbody.appendChild(newRow());
        renumber();
    });

    document.getElementById('items-body').addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row')) {
            const rows = document.querySelectorAll('#items-body .item-row');
            if (rows.length > 1) {
                e.target.closest('tr').remove();
                renumber();
            }
        }
    });

    renumber();
})();
</script>
@endsection
