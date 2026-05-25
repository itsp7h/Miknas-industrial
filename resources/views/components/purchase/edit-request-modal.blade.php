@props(['purchaseRequest'])

@php
$prId   = $purchaseRequest->id;
$prefix = 'mprEdit' . $prId;

// Re-open on validation error only when THIS request was being edited
$repop = $errors->any() && (string) old('_edit_request_id') === (string) $prId;

// Load items if not already eager-loaded
$prItems      = $purchaseRequest->relationLoaded('items') ? $purchaseRequest->items : $purchaseRequest->load('items')->items;
$existingItems = $repop && old('items')
    ? collect(old('items'))->map(fn($i) => (object)$i)
    : $prItems;

// Current field values (old() values take priority when repopulating)
$curDate    = $repop ? old('date',               $purchaseRequest->date ? \Carbon\Carbon::parse($purchaseRequest->date)->format('Y-m-d') : '') : ($purchaseRequest->date ? \Carbon\Carbon::parse($purchaseRequest->date)->format('Y-m-d') : '');
$curProject = $repop ? old('project_name',       $purchaseRequest->project_name       ?? '') : ($purchaseRequest->project_name       ?? '');
$curReqBy   = $repop ? old('requested_by_name',  $purchaseRequest->requested_by_name  ?? '') : ($purchaseRequest->requested_by_name  ?? '');
$curReqDate = $repop ? old('required_date_text', $purchaseRequest->required_date_text ?? '') : ($purchaseRequest->required_date_text ?? '');
$curLoc     = $repop ? old('location',           $purchaseRequest->location           ?? '') : ($purchaseRequest->location           ?? '');
$curDept    = $repop ? old('department',         $purchaseRequest->department         ?? '') : ($purchaseRequest->department         ?? '');
$curRemarks = $repop ? old('remarks',            $purchaseRequest->remarks            ?? '') : ($purchaseRequest->remarks            ?? '');

// Projects & locations for cascading dropdowns
$editProjects = \App\Models\Settings\ProjectSetting::active()
    ->with(['locations' => function ($q) { $q->where('is_active', true)->orderBy('name'); }])
    ->orderBy('name')
    ->get();

$editProjectsData = $editProjects->map(function ($p) {
    return [
        'id'        => $p->id,
        'name'      => $p->name,
        'locations' => $p->locations->map(fn($l) => ['name' => $l->name])->values()->toArray(),
    ];
})->values()->toArray();
$editProjectsJson = json_encode($editProjectsData);

// Check if current project is in the list
$curProjectInList = $editProjects->contains('name', $curProject);
@endphp

{{-- ── Trigger button ── --}}
<button type="button" onclick="{{ $prefix }}Open()" class="btn-secondary btn-sm">
    Edit
</button>

{{-- ── Modal overlay ── --}}
<div id="{{ $prefix }}Overlay"
     onclick="if(event.target===this){{ $prefix }}Close()"
     style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,0.55);backdrop-filter:blur(3px);">

    <div style="width:100%;max-width:58rem;max-height:88vh;display:flex;flex-direction:column;background:white;border-radius:1.25rem;box-shadow:0 25px 60px -10px rgba(0,0,0,0.3),0 10px 20px -5px rgba(0,0,0,0.15);">

        {{-- Header --}}
        <div style="flex-shrink:0;padding:1.25rem 1.5rem;border-radius:1.25rem 1.25rem 0 0;background:linear-gradient(135deg,#0f766e 0%,#0284c7 100%);display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:0.875rem;">
                <div style="background:rgba(255,255,255,0.15);border-radius:0.625rem;padding:0.5rem;">
                    <svg style="width:1.25rem;height:1.25rem;stroke:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
                <div>
                    <h2 style="color:white;font-size:1rem;font-weight:700;line-height:1.2;">Edit Purchase Request</h2>
                    <p style="color:#bae6fd;font-size:0.7rem;margin-top:0.1rem;">{{ $purchaseRequest->request_number }}</p>
                </div>
            </div>
            <button onclick="{{ $prefix }}Close()" type="button"
                    style="color:white;background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.25rem;line-height:1;transition:background 0.15s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.15)'">&times;</button>
        </div>

        {{-- Scrollable body --}}
        <div style="flex:1;overflow-y:auto;padding:1.5rem;">

            @if($repop)
            <div style="margin-bottom:1.25rem;padding:0.875rem 1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:0.75rem;font-size:0.8rem;color:#b91c1c;">
                <p style="font-weight:600;margin-bottom:0.25rem;">Please fix the following:</p>
                <ul style="list-style:disc;padding-left:1.25rem;line-height:1.8;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('purchase.requests.update', $purchaseRequest) }}" method="POST" id="{{ $prefix }}Form">
                @csrf
                @method('PUT')
                <input type="hidden" name="_edit_request_id" value="{{ $prId }}">

                {{-- Project / Department --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;">
                    <h3 style="font-size:0.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:1rem;display:flex;align-items:center;gap:0.4rem;">
                        <span style="display:inline-block;width:3px;height:12px;background:#0ea5e9;border-radius:2px;"></span>
                        Project / Department Details
                    </h3>
                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;">
                        <div>
                            <label class="form-label">Date <span class="text-red-500">*</span></label>
                            <input type="date" name="date" value="{{ $curDate }}" required class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Project / Site Name <span class="text-red-500">*</span></label>
                            <select name="project_name" id="{{ $prefix }}Project" required class="form-input"
                                    onchange="{{ $prefix }}FilterLoc(this.value)">
                                <option value="">— Select Project —</option>
                                @if($curProject && !$curProjectInList)
                                    <option value="{{ $curProject }}" selected>{{ $curProject }}</option>
                                @endif
                                @foreach($editProjects as $proj)
                                    <option value="{{ $proj->name }}" {{ $curProject === $proj->name ? 'selected' : '' }}>{{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Requested By <span class="text-red-500">*</span></label>
                            <input type="text" name="requested_by_name" value="{{ $curReqBy }}" required class="form-input" placeholder="Person's name">
                        </div>
                        <div>
                            <label class="form-label">Required Date / Urgency</label>
                            <input type="text" name="required_date_text" value="{{ $curReqDate }}" class="form-input" placeholder="e.g. Urgent, or 2026-06-01">
                        </div>
                        <div>
                            <label class="form-label">Location / Site</label>
                            <select name="location" id="{{ $prefix }}Location" class="form-input">
                                <option value="">— Select Location —</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Department</label>
                            <input type="text" name="department" value="{{ $curDept }}" class="form-input" placeholder="e.g. Operations">
                        </div>
                    </div>
                </div>

                {{-- Material Items --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                        <h3 style="font-size:0.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;display:flex;align-items:center;gap:0.4rem;">
                            <span style="display:inline-block;width:3px;height:12px;background:#0ea5e9;border-radius:2px;"></span>
                            Material Details
                        </h3>
                        <button type="button" id="{{ $prefix }}AddRow" class="btn-primary btn-sm">+ Add Item</button>
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
                            <tbody id="{{ $prefix }}Items">
                                @foreach($existingItems as $idx => $item)
                                @php
                                    $iArr  = is_array($item) ? $item : ($item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array)$item);
                                    $iDate = !empty($iArr['required_date']) ? \Carbon\Carbon::parse($iArr['required_date'])->format('Y-m-d') : '';
                                @endphp
                                <tr class="{{ $prefix }}-item-row" style="background:white;">
                                    <td style="border:1px solid #e2e8f0;padding:0.375rem 0.625rem;text-align:center;color:#cbd5e1;font-size:0.75rem;" class="{{ $prefix }}-row-num">{{ $idx + 1 }}</td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="text" name="items[{{ $idx }}][description]" value="{{ $iArr['description'] ?? '' }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Material description" required>
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="text" name="items[{{ $idx }}][unit]" value="{{ $iArr['unit'] ?? '' }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="PCS…">
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="number" name="items[{{ $idx }}][quantity_required]" value="{{ $iArr['quantity_required'] ?? '' }}"
                                               min="0.01" step="0.01" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="0" required>
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="text" name="items[{{ $idx }}][purpose_use]" value="{{ $iArr['purpose_use'] ?? '' }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Purpose…">
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;">
                                        <input type="date" name="items[{{ $idx }}][required_date]" value="{{ $iDate }}"
                                               style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;">
                                    </td>
                                    <td style="border:1px solid #e2e8f0;padding:0.25rem 0.4rem;text-align:center;">
                                        <button type="button" class="{{ $prefix }}-remove-row"
                                                style="color:#f87171;background:none;border:none;cursor:pointer;font-size:1.1rem;font-weight:700;line-height:1;padding:0.125rem 0.25rem;border-radius:0.25rem;"
                                                onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#f87171'">&times;</button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Remarks --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.25rem;">
                    <h3 style="font-size:0.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem;">
                        <span style="display:inline-block;width:3px;height:12px;background:#0ea5e9;border-radius:2px;"></span>
                        Remarks / Notes
                    </h3>
                    <textarea name="remarks" rows="2" class="form-textarea">{{ $curRemarks }}</textarea>
                </div>

            </form>
        </div>

        {{-- Footer --}}
        <div style="flex-shrink:0;padding:1rem 1.5rem;border-top:1px solid #f1f5f9;border-radius:0 0 1.25rem 1.25rem;background:#f8fafc;display:flex;align-items:center;gap:0.75rem;">
            <button type="submit" form="{{ $prefix }}Form" class="btn-primary">Save Changes</button>
            <button type="button" onclick="{{ $prefix }}Close()" class="btn-secondary">Cancel</button>
        </div>

    </div>
</div>

<script>
(function () {
    function {{ $prefix }}Open() {
        var el = document.getElementById('{{ $prefix }}Overlay');
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function {{ $prefix }}Close() {
        var el = document.getElementById('{{ $prefix }}Overlay');
        el.style.display = 'none';
        document.body.style.overflow = '';
    }
    window.{{ $prefix }}Open  = {{ $prefix }}Open;
    window.{{ $prefix }}Close = {{ $prefix }}Close;

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.getElementById('{{ $prefix }}Overlay').style.display === 'flex') {
            {{ $prefix }}Close();
        }
    });

    // Auto-open on validation error for this request
    @if($repop)
    document.addEventListener('DOMContentLoaded', function () { {{ $prefix }}Open(); });
    @endif

    // ── Dynamic rows ──────────────────────────────────────────────────────────
    var rowIdx = {{ $existingItems->count() }};

    function renumber() {
        document.querySelectorAll('#{{ $prefix }}Items .{{ $prefix }}-row-num').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    function newRow() {
        var idx = rowIdx++;
        var tr  = document.createElement('tr');
        tr.className = '{{ $prefix }}-item-row';
        tr.style.background = 'white';
        tr.innerHTML =
            '<td style="border:1px solid #e2e8f0;padding:0.375rem 0.625rem;text-align:center;color:#cbd5e1;font-size:0.75rem;" class="{{ $prefix }}-row-num"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][description]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Material description" required></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][unit]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="PCS…"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="number" name="items[' + idx + '][quantity_required]" min="0.01" step="0.01" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="0" required></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][purpose_use]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Purpose…"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="date" name="items[' + idx + '][required_date]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.4rem;text-align:center;"><button type="button" class="{{ $prefix }}-remove-row" style="color:#f87171;background:none;border:none;cursor:pointer;font-size:1.1rem;font-weight:700;line-height:1;" onmouseover="this.style.color=\'#dc2626\'" onmouseout="this.style.color=\'#f87171\'">&times;</button></td>';
        return tr;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var addBtn = document.getElementById('{{ $prefix }}AddRow');
        var tbody  = document.getElementById('{{ $prefix }}Items');
        if (!addBtn || !tbody) return;

        addBtn.addEventListener('click', function () {
            tbody.appendChild(newRow());
            renumber();
        });

        tbody.addEventListener('click', function (e) {
            if (e.target.classList.contains('{{ $prefix }}-remove-row')) {
                if (tbody.querySelectorAll('.{{ $prefix }}-item-row').length > 1) {
                    e.target.closest('tr').remove();
                    renumber();
                }
            }
        });

        renumber();
    });

    // ── Cascading project → location dropdown ─────────────────────────────────
    var _editProjects{{ $prId }} = {!! $editProjectsJson !!};
    var _curLoc{{ $prId }}       = {!! json_encode($curLoc) !!};

    function {{ $prefix }}FilterLoc(projectName) {
        var sel = document.getElementById('{{ $prefix }}Location');
        sel.innerHTML = '<option value="">— Select Location —</option>';
        if (!projectName) { sel.disabled = true; return; }
        var proj = _editProjects{{ $prId }}.find(function (p) { return p.name === projectName; });
        if (proj && proj.locations.length) {
            proj.locations.forEach(function (loc) {
                var opt = document.createElement('option');
                opt.value       = loc.name;
                opt.textContent = loc.name;
                sel.appendChild(opt);
            });
            sel.disabled = false;
        } else {
            sel.disabled = true;
        }
    }
    window.{{ $prefix }}FilterLoc = {{ $prefix }}FilterLoc;

    // On load: populate locations and pre-select the current ones
    document.addEventListener('DOMContentLoaded', function () {
        var curProj = document.getElementById('{{ $prefix }}Project').value;
        if (curProj) {
            {{ $prefix }}FilterLoc(curProj);
            var locSel  = document.getElementById('{{ $prefix }}Location');
            var matched = false;
            for (var i = 0; i < locSel.options.length; i++) {
                if (locSel.options[i].value === _curLoc{{ $prId }}) {
                    locSel.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            // If saved location not in project's list, add it as a selectable option
            if (!matched && _curLoc{{ $prId }}) {
                var opt = document.createElement('option');
                opt.value       = _curLoc{{ $prId }};
                opt.textContent = _curLoc{{ $prId }};
                opt.selected    = true;
                locSel.appendChild(opt);
                locSel.disabled = false;
            }
        } else {
            document.getElementById('{{ $prefix }}Location').disabled = true;
        }
    });
})();
</script>
