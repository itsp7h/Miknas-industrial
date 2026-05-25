@php
$hasErrors   = $errors->any();
$mprProjects = \App\Models\Settings\ProjectSetting::active()
    ->with(['locations' => function ($q) { $q->where('is_active', true)->orderBy('name'); }])
    ->orderBy('name')
    ->get();
$mprProjectsJson = $mprProjects->map(function ($p) {
    return [
        'id'        => $p->id,
        'name'      => $p->name,
        'locations' => $p->locations->map(function ($l) {
            return ['name' => $l->name];
        })->values(),
    ];
})->values();
@endphp

{{-- Trigger button --}}
<button type="button" onclick="mprModalOpen()" class="btn-primary">
    + New Request
</button>

{{-- ── Modal overlay ── --}}
<div id="mpr-modal-overlay"
     onclick="if(event.target===this) mprModalClose()"
     style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,0.55);backdrop-filter:blur(3px);">

    <div style="width:100%;max-width:58rem;max-height:88vh;display:flex;flex-direction:column;background:white;border-radius:1.25rem;box-shadow:0 25px 60px -10px rgba(0,0,0,0.3),0 10px 20px -5px rgba(0,0,0,0.15);">

        {{-- ── Header ── --}}
        <div style="flex-shrink:0;padding:1.25rem 1.5rem;border-radius:1.25rem 1.25rem 0 0;background:linear-gradient(135deg,#2563eb 0%,#4f46e5 100%);display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:0.875rem;">
                <div style="background:rgba(255,255,255,0.15);border-radius:0.625rem;padding:0.5rem;">
                    <svg style="width:1.25rem;height:1.25rem;stroke:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 style="color:white;font-size:1rem;font-weight:700;line-height:1.2;">New Purchase Request</h2>
                    <p style="color:#bfdbfe;font-size:0.7rem;margin-top:0.1rem;">Material Purchase Request (MPR)</p>
                </div>
            </div>
            <button onclick="mprModalClose()" type="button"
                    style="color:white;background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.25rem;line-height:1;transition:background 0.15s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.15)'">&times;</button>
        </div>

        {{-- ── Scrollable body ── --}}
        <div style="flex:1;overflow-y:auto;padding:1.5rem;">

            @if($errors->any())
            <div style="margin-bottom:1.25rem;padding:0.875rem 1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:0.75rem;font-size:0.8rem;color:#b91c1c;">
                <p style="font-weight:600;margin-bottom:0.25rem;">Please fix the following:</p>
                <ul style="list-style:disc;padding-left:1.25rem;line-height:1.8;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('purchase.requests.store') }}" method="POST" id="mpr-modal-form">
                @csrf

                {{-- Section: Project / Department --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;">
                    <h3 style="font-size:0.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:1rem;display:flex;align-items:center;gap:0.4rem;">
                        <span style="display:inline-block;width:3px;height:12px;background:#6366f1;border-radius:2px;"></span>
                        Project / Department Details
                    </h3>
                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;">
                        <div>
                            <label class="form-label">Date <span class="text-red-500">*</span></label>
                            <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Project / Site Name <span class="text-red-500">*</span></label>
                            <select name="project_name" id="mpr-project-select" required class="form-input" onchange="mprFilterLocations(this.value)">
                                <option value="">— Select Project —</option>
                                @foreach($mprProjects as $proj)
                                    <option value="{{ $proj->name }}" {{ old('project_name') == $proj->name ? 'selected' : '' }} data-id="{{ $proj->id }}">{{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Requested By <span class="text-red-500">*</span></label>
                            <input type="text" name="requested_by_name" value="{{ old('requested_by_name') }}" required class="form-input" placeholder="Person's name">
                        </div>
                        <div>
                            <label class="form-label">Required Date / Urgency</label>
                            <input type="text" name="required_date_text" value="{{ old('required_date_text') }}" class="form-input" placeholder="e.g. Urgent, or 2026-06-01">
                        </div>
                        <div>
                            <label class="form-label">Location / Site</label>
                            <select name="location" id="mpr-location-select" class="form-input">
                                <option value="">— Select Location —</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Department</label>
                            <input type="text" name="department" value="{{ old('department') }}" class="form-input" placeholder="e.g. Operations">
                        </div>
                    </div>
                </div>

                {{-- Section: Material Items --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                        <h3 style="font-size:0.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;display:flex;align-items:center;gap:0.4rem;">
                            <span style="display:inline-block;width:3px;height:12px;background:#6366f1;border-radius:2px;"></span>
                            Material Details
                        </h3>
                        <button type="button" id="mpr-add-row" class="btn-primary btn-sm">+ Add Item</button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                            <thead>
                                <tr style="background:white;">
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.625rem;text-align:left;width:2.5rem;font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;">#</th>
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.625rem;text-align:left;font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;">Description <span style="color:#f87171;">*</span></th>
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.625rem;text-align:left;width:5rem;font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;">Unit</th>
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.625rem;text-align:left;width:6rem;font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;">Qty <span style="color:#f87171;">*</span></th>
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.625rem;text-align:left;width:9rem;font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;">Purpose</th>
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.625rem;text-align:left;width:8rem;font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;">Req. Date</th>
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.4rem;width:2rem;"></th>
                                </tr>
                            </thead>
                            <tbody id="mpr-items-body">
                                @php $oldItems = old('items', [[]]); @endphp
                                @foreach($oldItems as $idx => $oldItem)
                                <tr class="mpr-item-row" style="background:white;">
                                    <td style="border:1px solid #e2e8f0;padding:0.375rem 0.625rem;text-align:center;color:#cbd5e1;font-size:0.75rem;" class="mpr-row-num">{{ $idx + 1 }}</td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="text" name="items[{{ $idx }}][description]" value="{{ $oldItem['description'] ?? '' }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Material description" required>
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="text" name="items[{{ $idx }}][unit]" value="{{ $oldItem['unit'] ?? '' }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="PCS…">
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="number" name="items[{{ $idx }}][quantity_required]" value="{{ $oldItem['quantity_required'] ?? '' }}"
                                               min="0.01" step="0.01" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="0" required>
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="text" name="items[{{ $idx }}][purpose_use]" value="{{ $oldItem['purpose_use'] ?? '' }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Purpose…">
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="date" name="items[{{ $idx }}][required_date]" value="{{ $oldItem['required_date'] ?? '' }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;">
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.4rem;text-align:center;">
                                        <button type="button" class="mpr-remove-row"
                                                style="color:#f87171;background:none;border:none;cursor:pointer;font-size:1.1rem;font-weight:700;line-height:1;padding:0.125rem 0.25rem;border-radius:0.25rem;"
                                                onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#f87171'">&times;</button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Section: Remarks --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.25rem;">
                    <h3 style="font-size:0.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem;">
                        <span style="display:inline-block;width:3px;height:12px;background:#6366f1;border-radius:2px;"></span>
                        Remarks / Notes
                    </h3>
                    <textarea name="remarks" rows="2" class="form-textarea">{{ old('remarks') }}</textarea>
                </div>

            </form>
        </div>

        {{-- ── Footer ── --}}
        <div style="flex-shrink:0;padding:1rem 1.5rem;border-top:1px solid #f1f5f9;border-radius:0 0 1.25rem 1.25rem;background:#f8fafc;display:flex;align-items:center;gap:0.75rem;">
            <button type="submit" form="mpr-modal-form" class="btn-primary">Submit Request</button>
            <button type="button" onclick="mprModalClose()" class="btn-secondary">Cancel</button>
        </div>

    </div>
</div>

<script>
function mprModalOpen() {
    var el = document.getElementById('mpr-modal-overlay');
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function mprModalClose() {
    var el = document.getElementById('mpr-modal-overlay');
    el.style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') mprModalClose();
});

// Auto-open if there are validation errors
@if($errors->any())
document.addEventListener('DOMContentLoaded', function () { mprModalOpen(); });
@endif

// Dynamic rows
(function () {
    var mprRowIndex = {{ count($oldItems ?? [[]]) }};

    function mprRenumber() {
        document.querySelectorAll('#mpr-items-body .mpr-row-num').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    function mprNewRow() {
        var idx = mprRowIndex++;
        var tr = document.createElement('tr');
        tr.className = 'mpr-item-row';
        tr.style.background = 'white';
        tr.innerHTML =
            '<td style="border:1px solid #e2e8f0;padding:0.375rem 0.625rem;text-align:center;color:#cbd5e1;font-size:0.75rem;" class="mpr-row-num"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][description]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Material description" required></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][unit]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="PCS…"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="number" name="items[' + idx + '][quantity_required]" min="0.01" step="0.01" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="0" required></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][purpose_use]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Purpose…"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="date" name="items[' + idx + '][required_date]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.4rem;text-align:center;"><button type="button" class="mpr-remove-row" style="color:#f87171;background:none;border:none;cursor:pointer;font-size:1.1rem;font-weight:700;line-height:1;" onmouseover="this.style.color=\'#dc2626\'" onmouseout="this.style.color=\'#f87171\'">&times;</button></td>';
        return tr;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var addBtn = document.getElementById('mpr-add-row');
        var tbody  = document.getElementById('mpr-items-body');
        if (!addBtn || !tbody) return;

        addBtn.addEventListener('click', function () {
            tbody.appendChild(mprNewRow());
            mprRenumber();
        });

        tbody.addEventListener('click', function (e) {
            if (e.target.classList.contains('mpr-remove-row')) {
                if (tbody.querySelectorAll('.mpr-item-row').length > 1) {
                    e.target.closest('tr').remove();
                    mprRenumber();
                }
            }
        });

        mprRenumber();
    });
})();

// Cascading project → location dropdown
var mprProjectsData = @json($mprProjectsJson);
var mprOldLocation = "{{ old('location') }}";
var mprOldProjectName = "{{ old('project_name') }}";

function mprFilterLocations(projectName) {
    var sel = document.getElementById('mpr-location-select');
    sel.innerHTML = '<option value="">— Select Location —</option>';
    if (!projectName) { sel.disabled = true; return; }
    var proj = mprProjectsData.find(function(p){ return p.name === projectName; });
    if (proj && proj.locations.length) {
        proj.locations.forEach(function(loc){
            var opt = document.createElement('option');
            opt.value = loc.name;
            opt.textContent = loc.name;
            if (loc.name === mprOldLocation) opt.selected = true;
            sel.appendChild(opt);
        });
        sel.disabled = false;
    } else {
        sel.disabled = true;
    }
}

// On load: if old project value exists (validation error repopulation), trigger filter
document.addEventListener('DOMContentLoaded', function(){
    if (mprOldProjectName) {
        mprFilterLocations(mprOldProjectName);
    } else {
        document.getElementById('mpr-location-select').disabled = true;
    }
});
</script>
