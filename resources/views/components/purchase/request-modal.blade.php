@php
$hasErrors   = $errors->any();
$mprProjects = \App\Models\Settings\ProjectSetting::active()
    ->whereNotNull('company_id')
    ->with([
        'company',
        'locations' => function ($q) { $q->where('is_active', true)->orderBy('name'); },
    ])
    ->orderBy('name')
    ->get();
$mprProjectsJson = $mprProjects->map(function ($p) {
    return [
        'id'           => $p->id,
        'name'         => $p->name,
        'company_id'   => $p->company_id,
        'company_name' => $p->company ? $p->company->name : '',
        'label'        => ($p->company ? $p->company->name . ' — ' : '') . $p->name,
        'locations'    => $p->locations->map(function ($l) {
            return ['name' => $l->name];
        })->values(),
    ];
})->values();

$mprDepartments = \App\Models\Settings\Department::where('is_active', true)->orderBy('name')->get();
$mprDepartmentsJson = $mprDepartments->map(fn($d) => ['id' => $d->id, 'name' => $d->name, 'company_id' => $d->company_id])->values();

$mprUnits = ['PCS','NOS','KG','TON','MTR','SQM','LTR','BAG','BOX','ROLL','SET','EA','CANS','LOT'];
$today    = date('Y-m-d');
@endphp

<style>
.mpr-proj-opt:hover { background:#eff6ff; border-left-color:#2563eb !important; }
.mpr-urgency-opt:hover { background:#f8fafc; }
</style>

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
                            <input type="date" name="date" value="{{ old('date', $today) }}" required class="form-input">
                        </div>
                        <div style="position:relative;" id="mpr-project-wrapper">
                            <label class="form-label">Project / Site Name <span class="text-red-500">*</span></label>
                            {{-- Hidden input carries the actual value for form submission --}}
                            <input type="hidden" name="project_name" id="mpr-project-value" value="{{ old('project_name') }}">
                            {{-- Trigger --}}
                            <button type="button" id="mpr-project-trigger"
                                    onclick="mprProjectToggle()"
                                    style="width:100%;text-align:left;background:white;border:1px solid #d1d5db;border-radius:0.375rem;padding:0.4rem 2rem 0.4rem 0.625rem;font-size:0.875rem;color:#111827;cursor:pointer;position:relative;line-height:1.5;min-height:2.25rem;">
                                <span id="mpr-project-label" style="color:#9ca3af;">— Select Project —</span>
                                <span style="position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);pointer-events:none;color:#6b7280;">&#9660;</span>
                            </button>
                            {{-- Dropdown panel --}}
                            <div id="mpr-project-panel"
                                 style="display:none;position:absolute;z-index:9999;top:calc(100% + 2px);left:0;right:0;background:white;border:1px solid #d1d5db;border-radius:0.5rem;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);overflow:hidden;">
                                <div style="padding:0.5rem;">
                                    <input type="text" id="mpr-project-search" placeholder="Search project or company…"
                                           oninput="mprProjectSearch(this.value)"
                                           style="width:100%;border:1px solid #e2e8f0;border-radius:0.375rem;padding:0.375rem 0.625rem;font-size:0.8rem;outline:none;box-sizing:border-box;">
                                </div>
                                <ul id="mpr-project-list"
                                    style="max-height:13rem;overflow-y:auto;margin:0;padding:0 0 0.25rem 0;list-style:none;">
                                    @foreach($mprProjects as $proj)
                                    <li class="mpr-proj-opt"
                                        data-value="{{ $proj->name }}"
                                        data-label="{{ ($proj->company ? $proj->company->name . ' — ' : '') . $proj->name }}"
                                        data-search="{{ strtolower(($proj->company ? $proj->company->name . ' ' : '') . $proj->name) }}"
                                        onclick="mprProjectSelect('{{ $proj->name }}', '{{ addslashes(($proj->company ? $proj->company->name . ' — ' : '') . $proj->name) }}')"
                                        style="padding:0.45rem 0.875rem;cursor:pointer;font-size:0.8rem;color:#111827;line-height:1.4;border-left:3px solid transparent;">
                                        <span style="font-size:0.68rem;color:#6b7280;display:block;line-height:1.2;">{{ $proj->company ? $proj->company->name : '' }}</span>
                                        {{ $proj->name }}
                                    </li>
                                    @endforeach
                                    <li id="mpr-proj-empty" style="display:none;padding:0.625rem 0.875rem;font-size:0.8rem;color:#9ca3af;">No projects found.</li>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Requested By <span class="text-red-500">*</span></label>
                            <input type="text" name="requested_by_name" value="{{ old('requested_by_name') }}" required class="form-input" placeholder="Person's name">
                        </div>
                        <div style="position:relative;" id="mpr-urgency-wrapper">
                            <label class="form-label">Required Date / Urgency</label>
                            <input type="hidden" name="required_date_text" id="mpr-urgency-value" value="{{ old('required_date_text') }}">
                            {{-- Trigger --}}
                            <button type="button" id="mpr-urgency-trigger" onclick="mprUrgencyToggle()"
                                    style="width:100%;text-align:left;background:white;border:1.5px dashed #d1d5db;border-radius:0.5rem;padding:0.45rem 2rem 0.45rem 0.75rem;font-size:0.8rem;cursor:pointer;position:relative;min-height:2.35rem;display:flex;align-items:center;transition:border-color 0.15s;">
                                <span id="mpr-urgency-display" style="display:flex;align-items:center;gap:0.5rem;flex:1;">
                                    <span style="color:#9ca3af;font-size:0.8rem;">— Select urgency —</span>
                                </span>
                                <span style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.7rem;">&#9660;</span>
                            </button>
                            {{-- Panel --}}
                            <div id="mpr-urgency-panel"
                                 style="display:none;position:absolute;z-index:10000;top:calc(100% + 4px);left:0;right:0;background:white;border:1px solid #e2e8f0;border-radius:0.75rem;box-shadow:0 12px 32px -4px rgba(0,0,0,0.18);overflow:hidden;">
                                <ul style="list-style:none;margin:0;padding:0.375rem;">
                                    <li onclick="mprUrgencySelect('Urgent','Urgent','#dc2626','#fef2f2')"
                                        class="mpr-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;transition:background 0.1s;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#fef2f2;display:flex;align-items:center;justify-content:center;">
                                            <span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#dc2626;"></span>
                                        </span>
                                        <div>
                                            <div style="font-size:0.8rem;font-weight:700;color:#dc2626;line-height:1.2;">Urgent</div>
                                            <div style="font-size:0.68rem;color:#9ca3af;">Needed immediately</div>
                                        </div>
                                    </li>
                                    <li onclick="mprUrgencySelect('3 Days','3 Days','#ea580c','#fff7ed')"
                                        class="mpr-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;transition:background 0.1s;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#fff7ed;display:flex;align-items:center;justify-content:center;">
                                            <span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#ea580c;"></span>
                                        </span>
                                        <div>
                                            <div style="font-size:0.8rem;font-weight:700;color:#ea580c;line-height:1.2;">3 Days</div>
                                            <div style="font-size:0.68rem;color:#9ca3af;">Within 3 days</div>
                                        </div>
                                    </li>
                                    <li onclick="mprUrgencySelect('1 Week','1 Week','#d97706','#fffbeb')"
                                        class="mpr-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;transition:background 0.1s;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#fffbeb;display:flex;align-items:center;justify-content:center;">
                                            <span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#d97706;"></span>
                                        </span>
                                        <div>
                                            <div style="font-size:0.8rem;font-weight:700;color:#d97706;line-height:1.2;">1 Week</div>
                                            <div style="font-size:0.68rem;color:#9ca3af;">Within this week</div>
                                        </div>
                                    </li>
                                    <li onclick="mprUrgencySelect('2 Weeks','2 Weeks','#2563eb','#eff6ff')"
                                        class="mpr-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;transition:background 0.1s;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                                            <span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#2563eb;"></span>
                                        </span>
                                        <div>
                                            <div style="font-size:0.8rem;font-weight:700;color:#2563eb;line-height:1.2;">2 Weeks</div>
                                            <div style="font-size:0.68rem;color:#9ca3af;">Within 2 weeks</div>
                                        </div>
                                    </li>
                                    <li onclick="mprUrgencySelect('1 Month','1 Month','#16a34a','#f0fdf4')"
                                        class="mpr-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;margin-bottom:0.125rem;transition:background 0.1s;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
                                            <span style="width:0.55rem;height:0.55rem;border-radius:50%;background:#16a34a;"></span>
                                        </span>
                                        <div>
                                            <div style="font-size:0.8rem;font-weight:700;color:#16a34a;line-height:1.2;">1 Month</div>
                                            <div style="font-size:0.68rem;color:#9ca3af;">Within a month</div>
                                        </div>
                                    </li>
                                    <li style="height:1px;background:#f1f5f9;margin:0.25rem 0;"></li>
                                    <li id="mpr-urgency-date-opt" onclick="mprUrgencyDateOptClick()"
                                        class="mpr-urgency-opt"
                                        style="display:flex;align-items:center;gap:0.625rem;padding:0.55rem 0.75rem;border-radius:0.5rem;cursor:pointer;transition:background 0.1s;">
                                        <span style="flex-shrink:0;width:2rem;height:2rem;border-radius:0.4rem;background:#f5f3ff;display:flex;align-items:center;justify-content:center;">
                                            <svg style="width:0.875rem;height:0.875rem;stroke:#7c3aed;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </span>
                                        <div style="flex:1;">
                                            <div style="font-size:0.8rem;font-weight:700;color:#7c3aed;line-height:1.2;">Specific Date</div>
                                            <div style="font-size:0.68rem;color:#9ca3af;">Pick an exact date</div>
                                        </div>
                                    </li>
                                    <li id="mpr-urgency-datepicker-row" style="display:none;padding:0 0.75rem 0.5rem;">
                                        <input type="date" id="mpr-urgency-date"
                                               style="width:100%;font-size:0.8rem;border:1.5px solid #7c3aed;border-radius:0.4rem;padding:0.35rem 0.5rem;outline:none;color:#7c3aed;box-sizing:border-box;"
                                               onchange="mprUrgencyDateChange(this.value)">
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Location / Site</label>
                            <select name="location" id="mpr-location-select" class="form-input">
                                <option value="">— Select Location —</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Department</label>
                            <select name="department" id="mpr-department-select" class="form-input">
                                <option value="">— Select Department —</option>
                                @foreach($mprDepartments as $dept)
                                    <option value="{{ $dept->name }}" data-company="{{ $dept->company_id }}" {{ old('department') == $dept->name ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
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
                                    <th style="border:1px solid #e2e8f0;padding:0.5rem 0.625rem;text-align:left;width:6rem;font-size:0.65rem;font-weight:600;color:#94a3b8;text-transform:uppercase;">Unit</th>
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
                                        <select name="items[{{ $idx }}][unit]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;cursor:pointer;">
                                            <option value="">—</option>
                                            @foreach($mprUnits as $u)
                                                <option value="{{ $u }}" {{ ($oldItem['unit'] ?? '') == $u ? 'selected' : '' }}>{{ $u }}</option>
                                            @endforeach
                                        </select>
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
                                        <input type="date" name="items[{{ $idx }}][required_date]" value="{{ $oldItem['required_date'] ?? $today }}"
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

// Unit options HTML (shared between template rows and JS-added rows)
var mprUnitOptions = '<option value="">—</option>' +
    @foreach($mprUnits as $u)
    '<option value="{{ $u }}">{{ $u }}</option>' +
    @endforeach
    '';

// Dynamic rows
(function () {
    var mprRowIndex = {{ count($oldItems ?? [[]]) }};

    function mprRenumber() {
        document.querySelectorAll('#mpr-items-body .mpr-row-num').forEach(function (el, i) {
            el.textContent = i + 1;
        });
    }

    function mprLastRowDate() {
        var rows = document.querySelectorAll('#mpr-items-body .mpr-item-row');
        if (!rows.length) return '{{ $today }}';
        var lastDate = rows[rows.length - 1].querySelector('input[type="date"]');
        return (lastDate && lastDate.value) ? lastDate.value : '{{ $today }}';
    }

    function mprNewRow() {
        var idx  = mprRowIndex++;
        var date = mprLastRowDate();
        var tr   = document.createElement('tr');
        tr.className = 'mpr-item-row';
        tr.style.background = 'white';
        tr.innerHTML =
            '<td style="border:1px solid #e2e8f0;padding:0.375rem 0.625rem;text-align:center;color:#cbd5e1;font-size:0.75rem;" class="mpr-row-num"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][description]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Material description" required></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><select name="items[' + idx + '][unit]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;cursor:pointer;">' + mprUnitOptions + '</select></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="number" name="items[' + idx + '][quantity_required]" min="0.01" step="0.01" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="0" required></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="text" name="items[' + idx + '][purpose_use]" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;" placeholder="Purpose…"></td>' +
            '<td style="border:1px solid #e2e8f0;padding:0.25rem 0.5rem;"><input type="date" name="items[' + idx + '][required_date]" value="' + date + '" style="width:100%;border:0;outline:none;font-size:0.8rem;background:transparent;"></td>' +
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

// ── Cascading data ────────────────────────────────────────────────────────────
var mprProjectsData    = @json($mprProjectsJson);
var mprDepartmentsData = @json($mprDepartmentsJson);
var mprOldLocation     = "{{ old('location') }}";
var mprOldProjectName  = "{{ old('project_name') }}";
var mprOldDepartment   = "{{ old('department') }}";

// ── Searchable project dropdown ───────────────────────────────────────────────
function mprProjectToggle() {
    var panel = document.getElementById('mpr-project-panel');
    var open  = panel.style.display !== 'none';
    if (open) {
        mprProjectClose();
    } else {
        panel.style.display = 'block';
        var search = document.getElementById('mpr-project-search');
        search.value = '';
        mprProjectSearch('');
        setTimeout(function(){ search.focus(); }, 30);
    }
}

function mprProjectClose() {
    document.getElementById('mpr-project-panel').style.display = 'none';
}

function mprProjectSearch(q) {
    var items   = document.querySelectorAll('#mpr-project-list .mpr-proj-opt');
    var empty   = document.getElementById('mpr-proj-empty');
    var term    = q.trim().toLowerCase();
    var visible = 0;
    items.forEach(function(li) {
        var match = !term || li.dataset.search.indexOf(term) !== -1;
        li.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    empty.style.display = visible === 0 ? '' : 'none';
}

function mprProjectSelect(value, label) {
    document.getElementById('mpr-project-value').value = value;
    var lbl = document.getElementById('mpr-project-label');
    lbl.textContent = label;
    lbl.style.color = '#111827';
    mprProjectClose();
    mprOnProjectChange(value);
}

// Close panel when clicking outside
document.addEventListener('click', function(e) {
    var wrapper = document.getElementById('mpr-project-wrapper');
    if (wrapper && !wrapper.contains(e.target)) mprProjectClose();
});

// ── Location cascade ──────────────────────────────────────────────────────────
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

// ── Department cascade ────────────────────────────────────────────────────────
function mprFilterDepartments(projectName) {
    var sel = document.getElementById('mpr-department-select');
    sel.innerHTML = '<option value="">— Select Department —</option>';
    var companyId = null;
    if (projectName) {
        var proj = mprProjectsData.find(function(p){ return p.name === projectName; });
        if (proj) companyId = proj.company_id;
    }
    var depts = companyId
        ? mprDepartmentsData.filter(function(d){ return d.company_id === companyId; })
        : mprDepartmentsData;
    depts.forEach(function(d){
        var opt = document.createElement('option');
        opt.value = d.name;
        opt.textContent = d.name;
        if (d.name === mprOldDepartment) opt.selected = true;
        sel.appendChild(opt);
    });
    sel.disabled = depts.length === 0;
}

function mprOnProjectChange(projectName) {
    mprFilterLocations(projectName);
    mprFilterDepartments(projectName);
}

// ── Restore old values on validation error repopulation ───────────────────────
document.addEventListener('DOMContentLoaded', function(){
    if (mprOldProjectName) {
        var proj = mprProjectsData.find(function(p){ return p.name === mprOldProjectName; });
        if (proj) mprProjectSelect(proj.name, proj.label);
    } else {
        document.getElementById('mpr-location-select').disabled = true;
        mprFilterDepartments('');
    }
    // Restore urgency pill
    var oldUrgency = document.getElementById('mpr-urgency-value').value;
    if (oldUrgency) mprUrgencyRestore(oldUrgency);
});

// ── Urgency colored dropdown ──────────────────────────────────────────────────
var mprUrgencyPresets = ['Urgent','3 Days','1 Week','2 Weeks','1 Month'];

function mprUrgencyToggle() {
    var panel = document.getElementById('mpr-urgency-panel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
    } else {
        mprUrgencyClose();
    }
}

function mprUrgencyClose() {
    document.getElementById('mpr-urgency-panel').style.display = 'none';
}

function mprUrgencySelect(value, label, color, bgColor) {
    document.getElementById('mpr-urgency-value').value = value;
    // Update trigger display
    var display = document.getElementById('mpr-urgency-display');
    display.innerHTML =
        '<span style="display:inline-flex;align-items:center;gap:0.4rem;background:' + bgColor + ';border:1px solid ' + color + '20;padding:0.15rem 0.6rem 0.15rem 0.4rem;border-radius:999px;">' +
        '<span style="width:0.45rem;height:0.45rem;border-radius:50%;background:' + color + ';flex-shrink:0;"></span>' +
        '<span style="font-size:0.78rem;font-weight:600;color:' + color + ';">' + label + '</span>' +
        '</span>';
    // Update trigger border color
    document.getElementById('mpr-urgency-trigger').style.borderColor = color;
    document.getElementById('mpr-urgency-trigger').style.borderStyle = 'solid';
    // Hide date picker row
    document.getElementById('mpr-urgency-datepicker-row').style.display = 'none';
    mprUrgencyClose();
}

function mprUrgencyDateOptClick() {
    var row = document.getElementById('mpr-urgency-datepicker-row');
    row.style.display = row.style.display === 'none' ? '' : 'none';
    if (row.style.display !== 'none') {
        document.getElementById('mpr-urgency-date').focus();
    }
}

function mprUrgencyDateChange(val) {
    if (!val) return;
    document.getElementById('mpr-urgency-value').value = val;
    // Format nicely for display
    var d = new Date(val + 'T00:00:00');
    var label = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
    var display = document.getElementById('mpr-urgency-display');
    display.innerHTML =
        '<span style="display:inline-flex;align-items:center;gap:0.4rem;background:#f5f3ff;border:1px solid #7c3aed20;padding:0.15rem 0.6rem 0.15rem 0.4rem;border-radius:999px;">' +
        '<svg style="width:0.7rem;height:0.7rem;stroke:#7c3aed;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
        '<span style="font-size:0.78rem;font-weight:600;color:#7c3aed;">' + label + '</span>' +
        '</span>';
    document.getElementById('mpr-urgency-trigger').style.borderColor = '#7c3aed';
    document.getElementById('mpr-urgency-trigger').style.borderStyle = 'solid';
    mprUrgencyClose();
}

// Close on outside click
document.addEventListener('click', function(e) {
    var w = document.getElementById('mpr-urgency-wrapper');
    if (w && !w.contains(e.target)) mprUrgencyClose();
});

// Restore on validation error repopulation
function mprUrgencyRestore(val) {
    if (!val) return;
    var presets = {
        'Urgent':  ['Urgent',  '#dc2626','#fef2f2'],
        '3 Days':  ['3 Days',  '#ea580c','#fff7ed'],
        '1 Week':  ['1 Week',  '#d97706','#fffbeb'],
        '2 Weeks': ['2 Weeks', '#2563eb','#eff6ff'],
        '1 Month': ['1 Month', '#16a34a','#f0fdf4'],
    };
    if (presets[val]) {
        mprUrgencySelect(val, presets[val][0], presets[val][1], presets[val][2]);
    } else {
        // It's a specific date string
        document.getElementById('mpr-urgency-value').value = val;
        document.getElementById('mpr-urgency-datepicker-row').style.display = '';
        document.getElementById('mpr-urgency-date').value = val;
        var d = new Date(val + 'T00:00:00');
        var label = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
        var display = document.getElementById('mpr-urgency-display');
        display.innerHTML =
            '<span style="display:inline-flex;align-items:center;gap:0.4rem;background:#f5f3ff;border:1px solid #7c3aed20;padding:0.15rem 0.6rem 0.15rem 0.4rem;border-radius:999px;">' +
            '<svg style="width:0.7rem;height:0.7rem;stroke:#7c3aed;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
            '<span style="font-size:0.78rem;font-weight:600;color:#7c3aed;">' + label + '</span>' +
            '</span>';
        document.getElementById('mpr-urgency-trigger').style.borderColor = '#7c3aed';
        document.getElementById('mpr-urgency-trigger').style.borderStyle = 'solid';
    }
}
</script>
