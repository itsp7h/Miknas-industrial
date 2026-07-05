@props(['purchaseRequest', 'size' => 'sm'])

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

<style>
.{{ $prefix }}-urgency-opt:hover { background:#f8fafc; }
</style>

{{-- ── Trigger button ── --}}
<button type="button" onclick="{{ $prefix }}Open()" class="btn-secondary {{ $size === 'sm' ? 'btn-sm' : '' }}">
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
                        <div style="position:relative;" id="{{ $prefix }}UrgencyWrapper">
                            <label class="form-label">Required Date / Urgency</label>
                            <input type="hidden" name="required_date_text" id="{{ $prefix }}UrgencyValue" value="{{ $curReqDate }}">
                            {{-- Trigger --}}
                            <button type="button" id="{{ $prefix }}UrgencyTrigger" onclick="{{ $prefix }}UrgencyToggle()"
                                    style="width:100%;text-align:left;background:white;border:1.5px dashed #d1d5db;border-radius:0.5rem;padding:0.45rem 2rem 0.45rem 0.75rem;font-size:0.8rem;cursor:pointer;position:relative;min-height:2.35rem;display:flex;align-items:center;transition:border-color 0.15s;">
                                <span id="{{ $prefix }}UrgencyDisplay" style="display:flex;align-items:center;gap:0.5rem;flex:1;">
                                    <span style="color:#9ca3af;font-size:0.8rem;">— Select urgency —</span>
                                </span>
                                <span style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.7rem;">&#9660;</span>
                            </button>
                            {{-- Panel --}}
                            <div id="{{ $prefix }}UrgencyPanel"
                                 style="display:none;position:absolute;z-index:10000;top:calc(100% + 4px);left:0;right:0;background:white;border:1px solid #e2e8f0;border-radius:0.75rem;box-shadow:0 12px 32px -4px rgba(0,0,0,0.18);overflow:hidden;">
                                <ul style="list-style:none;margin:0;padding:0.375rem;">
                                    <li onclick="{{ $prefix }}UrgencySelect('Urgent','Urgent','#dc2626','#fef2f2')"
                                        class="{{ $prefix }}-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#fef2f2;display:flex;align-items:center;justify-content:center;"><span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#dc2626;"></span></span>
                                        <div><div style="font-size:0.8rem;font-weight:700;color:#dc2626;line-height:1.2;">Urgent</div><div style="font-size:0.68rem;color:#9ca3af;">Needed immediately</div></div>
                                    </li>
                                    <li onclick="{{ $prefix }}UrgencySelect('3 Days','3 Days','#ea580c','#fff7ed')"
                                        class="{{ $prefix }}-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#fff7ed;display:flex;align-items:center;justify-content:center;"><span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#ea580c;"></span></span>
                                        <div><div style="font-size:0.8rem;font-weight:700;color:#ea580c;line-height:1.2;">3 Days</div><div style="font-size:0.68rem;color:#9ca3af;">Within 3 days</div></div>
                                    </li>
                                    <li onclick="{{ $prefix }}UrgencySelect('1 Week','1 Week','#d97706','#fffbeb')"
                                        class="{{ $prefix }}-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#fffbeb;display:flex;align-items:center;justify-content:center;"><span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#d97706;"></span></span>
                                        <div><div style="font-size:0.8rem;font-weight:700;color:#d97706;line-height:1.2;">1 Week</div><div style="font-size:0.68rem;color:#9ca3af;">Within this week</div></div>
                                    </li>
                                    <li onclick="{{ $prefix }}UrgencySelect('2 Weeks','2 Weeks','#2563eb','#eff6ff')"
                                        class="{{ $prefix }}-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#eff6ff;display:flex;align-items:center;justify-content:center;"><span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#2563eb;"></span></span>
                                        <div><div style="font-size:0.8rem;font-weight:700;color:#2563eb;line-height:1.2;">2 Weeks</div><div style="font-size:0.68rem;color:#9ca3af;">Within 2 weeks</div></div>
                                    </li>
                                    <li onclick="{{ $prefix }}UrgencySelect('1 Month','1 Month','#16a34a','#f0fdf4')"
                                        class="{{ $prefix }}-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#f0fdf4;display:flex;align-items:center;justify-content:center;"><span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#16a34a;"></span></span>
                                        <div><div style="font-size:0.8rem;font-weight:700;color:#16a34a;line-height:1.2;">1 Month</div><div style="font-size:0.68rem;color:#9ca3af;">Within a month</div></div>
                                    </li>
                                    <li style="height:1px;background:#f1f5f9;margin:0.25rem 0;"></li>
                                    <li id="{{ $prefix }}UrgencyDateOpt" onclick="{{ $prefix }}UrgencyDateOptClick()"
                                        class="{{ $prefix }}-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#f5f3ff;display:flex;align-items:center;justify-content:center;">
                                            <svg style="width:0.875rem;height:0.875rem;stroke:#7c3aed;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </span>
                                        <div style="flex:1;"><div style="font-size:0.8rem;font-weight:700;color:#7c3aed;line-height:1.2;">Specific Date</div><div style="font-size:0.68rem;color:#9ca3af;">Pick an exact date</div></div>
                                    </li>
                                    <li id="{{ $prefix }}UrgencyDatePickerRow" style="display:none;padding:0 0.75rem 0.5rem;">
                                        <input type="date" id="{{ $prefix }}UrgencyDate"
                                               style="width:100%;font-size:0.8rem;border:1.5px solid #7c3aed;border-radius:0.4rem;padding:0.35rem 0.5rem;outline:none;color:#7c3aed;box-sizing:border-box;"
                                               onchange="{{ $prefix }}UrgencyDateChange(this.value)">
                                    </li>
                                </ul>
                            </div>
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

    // ── Urgency colored dropdown ──────────────────────────────────────────────
    var _urgencyMap{{ $prId }} = {
        'Urgent':  ['Urgent',  '#dc2626','#fef2f2'],
        '3 Days':  ['3 Days',  '#ea580c','#fff7ed'],
        '1 Week':  ['1 Week',  '#d97706','#fffbeb'],
        '2 Weeks': ['2 Weeks', '#2563eb','#eff6ff'],
        '1 Month': ['1 Month', '#16a34a','#f0fdf4'],
    };

    function _urgencyBadge{{ $prId }}(label, color, bgColor) {
        return '<span style="display:inline-flex;align-items:center;gap:0.4rem;background:' + bgColor + ';border:1px solid ' + color + '20;padding:0.15rem 0.6rem 0.15rem 0.4rem;border-radius:999px;">' +
               '<span style="width:0.45rem;height:0.45rem;border-radius:50%;background:' + color + ';flex-shrink:0;"></span>' +
               '<span style="font-size:0.78rem;font-weight:600;color:' + color + ';">' + label + '</span></span>';
    }

    function _urgencyDateBadge{{ $prId }}(label) {
        return '<span style="display:inline-flex;align-items:center;gap:0.4rem;background:#f5f3ff;border:1px solid #7c3aed20;padding:0.15rem 0.6rem 0.15rem 0.4rem;border-radius:999px;">' +
               '<svg style="width:0.7rem;height:0.7rem;stroke:#7c3aed;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
               '<span style="font-size:0.78rem;font-weight:600;color:#7c3aed;">' + label + '</span></span>';
    }

    window.{{ $prefix }}UrgencyToggle = function() {
        var p = document.getElementById('{{ $prefix }}UrgencyPanel');
        p.style.display = p.style.display === 'none' ? 'block' : 'none';
    };

    window.{{ $prefix }}UrgencyClose = function() {
        document.getElementById('{{ $prefix }}UrgencyPanel').style.display = 'none';
    };

    window.{{ $prefix }}UrgencySelect = function(value, label, color, bgColor) {
        document.getElementById('{{ $prefix }}UrgencyValue').value = value;
        document.getElementById('{{ $prefix }}UrgencyDisplay').innerHTML = _urgencyBadge{{ $prId }}(label, color, bgColor);
        var t = document.getElementById('{{ $prefix }}UrgencyTrigger');
        t.style.borderColor = color; t.style.borderStyle = 'solid';
        document.getElementById('{{ $prefix }}UrgencyDatePickerRow').style.display = 'none';
        {{ $prefix }}UrgencyClose();
    };

    window.{{ $prefix }}UrgencyDateOptClick = function() {
        var row = document.getElementById('{{ $prefix }}UrgencyDatePickerRow');
        row.style.display = row.style.display === 'none' ? '' : 'none';
        if (row.style.display !== 'none') document.getElementById('{{ $prefix }}UrgencyDate').focus();
    };

    window.{{ $prefix }}UrgencyDateChange = function(val) {
        if (!val) return;
        document.getElementById('{{ $prefix }}UrgencyValue').value = val;
        var d = new Date(val + 'T00:00:00');
        var label = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
        document.getElementById('{{ $prefix }}UrgencyDisplay').innerHTML = _urgencyDateBadge{{ $prId }}(label);
        var t = document.getElementById('{{ $prefix }}UrgencyTrigger');
        t.style.borderColor = '#7c3aed'; t.style.borderStyle = 'solid';
        {{ $prefix }}UrgencyClose();
    };

    // Close on outside click
    document.addEventListener('click', function(e) {
        var w = document.getElementById('{{ $prefix }}UrgencyWrapper');
        if (w && !w.contains(e.target)) {{ $prefix }}UrgencyClose();
    });

    // Restore saved value on load
    document.addEventListener('DOMContentLoaded', function() {
        var val = document.getElementById('{{ $prefix }}UrgencyValue').value;
        if (!val) return;
        if (_urgencyMap{{ $prId }}[val]) {
            var m = _urgencyMap{{ $prId }}[val];
            {{ $prefix }}UrgencySelect(val, m[0], m[1], m[2]);
        } else {
            document.getElementById('{{ $prefix }}UrgencyDatePickerRow').style.display = '';
            document.getElementById('{{ $prefix }}UrgencyDate').value = val;
            var d2 = new Date(val + 'T00:00:00');
            var lbl = d2.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
            document.getElementById('{{ $prefix }}UrgencyDisplay').innerHTML = _urgencyDateBadge{{ $prId }}(lbl);
            var t2 = document.getElementById('{{ $prefix }}UrgencyTrigger');
            t2.style.borderColor = '#7c3aed'; t2.style.borderStyle = 'solid';
        }
    });

    // ── Cascading project → location dropdown ─────────────────────────────────
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
