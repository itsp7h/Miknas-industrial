@extends('layouts.app')

@section('title', 'Settings — Projects')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
    .proj-card { border:1px solid #e2e8f0; border-radius:0.875rem; overflow:hidden; margin-bottom:0.75rem; background:white; }
    .proj-header { display:flex; align-items:center; justify-content:space-between; padding:0.875rem 1.25rem; background:white; }
    .proj-body { display:block; border-top:1px solid #e2e8f0; }
    .proj-body-inner { display:grid; grid-template-columns:1fr 1fr; }
    .proj-section { border-right:1px solid #e2e8f0; }
    .proj-section:last-child { border-right:none; }
    .proj-section-header { display:flex; align-items:center; justify-content:space-between; padding:0.6rem 1.25rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
    .proj-section-title { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
    .loc-row { display:flex; align-items:flex-start; justify-content:space-between; padding:0.65rem 1rem 0.65rem 1.25rem; border-bottom:1px solid #f1f5f9; }
    .dept-row { display:flex; align-items:center; justify-content:space-between; padding:0.6rem 1rem 0.6rem 1.25rem; border-bottom:1px solid #f1f5f9; }
    .dept-edit-row { display:none; padding:0.5rem 1rem; background:#f0f9ff; border-bottom:1px solid #bae6fd; gap:6px; align-items:center; }
    .dept-edit-row.open { display:flex; }
    .proj-edit-wrap { display:none; padding:0.75rem 1.25rem; background:#f0f9ff; border-bottom:1px solid #bae6fd; align-items:center; gap:8px; }
    .proj-edit-wrap.open { display:flex; }
    .badge-inactive { display:inline-block; padding:1px 7px; border-radius:9px; font-size:11px; font-weight:600; background:#fee2e2; color:#dc2626; margin-left:6px; }
    .field-error { color:#dc2626; font-size:12px; margin-top:4px; display:none; }
    .stat-card { background:white; border:1px solid #e2e8f0; border-radius:0.75rem; padding:1.25rem 1.5rem; }
    /* Location modal */
    #loc-modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:9999; align-items:center; justify-content:center; }
    #loc-modal-overlay.open { display:flex; }
    #loc-modal-box { background:white; border-radius:1rem; width:940px; max-width:96vw; max-height:93vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 25px 60px rgba(0,0,0,0.25); }
    #loc-map { height:100%; width:100%; min-height:420px; }
    .leaflet-container { font-family:inherit; }
</style>

{{-- Page header --}}
<div class="mb-5" style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">Projects, Locations &amp; Departments</h1>
        <p class="page-subtitle">Manage projects with their sub-locations and departments.</p>
    </div>
</div>

{{-- Stat boxes --}}
<div style="display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:28px;">
    <div class="stat-card" style="border-top:3px solid #6366f1;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#eef2ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <div id="stat-companies" style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_companies'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Companies</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #3b82f6;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#3b82f6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            </div>
            <div>
                <div id="stat-projects" style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_projects'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Projects</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #8b5cf6;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#f5f3ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#8b5cf6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_locations'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Locations</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #06b6d4;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#ecfeff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#06b6d4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_departments'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Departments</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #22c55e;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#22c55e" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['active_projects'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Active Projects</div>
            </div>
        </div>
    </div>
</div>

{{-- Add Company --}}
<div class="card card-body mb-5" style="padding:1.125rem 1.25rem;">
    <div style="display:flex; gap:10px; align-items:flex-start;">
        <div style="flex:1;">
            <input id="new-company-input" type="text" class="form-input" style="width:100%;"
                placeholder="New company name…"
                onkeydown="if(event.key==='Enter') addCompany()">
            <p id="new-company-error" class="field-error"></p>
        </div>
        <button type="button" onclick="addCompany()" class="btn-primary" style="white-space:nowrap; flex-shrink:0; padding:0.5rem 1.25rem;">
            + Add Company
        </button>
    </div>
</div>

{{-- Build data maps for safe JS passing --}}
@php
$allLocsData  = [];
$allDeptsData = [];
foreach ($companies as $company) {
    foreach ($company->projects as $proj) {
        foreach ($proj->locations as $loc) {
            $allLocsData[$loc->id] = [
                'id'        => $loc->id,
                'name'      => $loc->name,
                'address'   => $loc->address,
                'latitude'  => $loc->latitude,
                'longitude' => $loc->longitude,
                'is_active' => $loc->is_active,
            ];
        }
        foreach ($proj->departments as $dept) {
            $allDeptsData[$dept->id] = [
                'id'        => $dept->id,
                'name'      => $dept->name,
                'is_active' => $dept->is_active,
            ];
        }
    }
}
$allLocsJson  = json_encode($allLocsData);
$allDeptsJson = json_encode($allDeptsData);
@endphp

{{-- Companies list --}}
<div id="companies-list">
@forelse($companies as $company)

{{-- Company card --}}
<div id="company-card-{{ $company->id }}" style="margin-bottom:1.25rem;">
    {{-- Company header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1.25rem;background:linear-gradient(135deg,#eef2ff,#e0e7ff);border:1px solid #c7d2fe;border-radius:0.875rem 0.875rem 0 0;border-bottom:none;">
        <div style="display:flex;align-items:center;gap:10px;">
            <svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <span id="company-name-{{ $company->id }}" style="font-size:15px;font-weight:700;color:#3730a3;">{{ $company->name }}</span>
            <span id="company-inactive-{{ $company->id }}" class="badge-inactive" style="{{ $company->is_active ? 'display:none' : '' }}">Inactive</span>
            <span id="company-proj-count-{{ $company->id }}" style="font-size:12px;color:#6366f1;opacity:.7;">{{ $company->projects->count() }} {{ Str::plural('project', $company->projects->count()) }}</span>
        </div>
        <div style="display:flex;gap:6px;align-items:center;">
            <button type="button" onclick="openAddProject({{ $company->id }})"
                class="btn-primary" style="padding:4px 12px;font-size:12px;">+ Add Project</button>
            <button type="button" onclick="openEditCompany({{ $company->id }}, '{{ addslashes($company->name) }}', {{ $company->is_active ? 'true' : 'false' }})"
                class="btn-secondary btn-sm">Edit</button>
            <button type="button" onclick="deleteCompany({{ $company->id }}, '{{ addslashes($company->name) }}')"
                class="btn-danger btn-sm">Delete</button>
        </div>
    </div>

    {{-- Company edit strip --}}
    <div class="proj-edit-wrap" id="company-edit-{{ $company->id }}" style="border-radius:0;border-top:1px solid #c7d2fe;border-left:1px solid #c7d2fe;border-right:1px solid #c7d2fe;">
        <input id="edit-company-name-{{ $company->id }}" type="text" class="form-input" style="flex:1;font-size:13px;">
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;">
            <input type="checkbox" id="edit-company-active-{{ $company->id }}" {{ $company->is_active ? 'checked' : '' }} style="width:14px;height:14px;">
            Active
        </label>
        <button type="button" onclick="saveCompany({{ $company->id }})" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>
        <button type="button" onclick="closeEditCompany({{ $company->id }})" class="btn-secondary btn-sm">Cancel</button>
        <p id="edit-company-error-{{ $company->id }}" class="field-error" style="margin:0;"></p>
    </div>

    {{-- Add project strip --}}
    <div class="proj-edit-wrap" id="add-proj-strip-{{ $company->id }}" style="border-radius:0;border-left:1px solid #c7d2fe;border-right:1px solid #c7d2fe;background:#f0fdf4;border-color:#bbf7d0;">
        <input id="add-proj-name-{{ $company->id }}" type="text" class="form-input" style="flex:1;font-size:13px;" placeholder="New project name…"
            onkeydown="if(event.key==='Enter') saveAddProject({{ $company->id }}); if(event.key==='Escape') closeAddProject({{ $company->id }})">
        <button type="button" onclick="saveAddProject({{ $company->id }})" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>
        <button type="button" onclick="closeAddProject({{ $company->id }})" class="btn-secondary btn-sm">Cancel</button>
        <p id="add-proj-error-{{ $company->id }}" class="field-error" style="margin:0;"></p>
    </div>

    {{-- Projects container --}}
    <div id="proj-list-{{ $company->id }}" style="border:1px solid #c7d2fe;border-top:none;border-radius:0 0 0.875rem 0.875rem;overflow:hidden;">

        @forelse($company->projects as $project)
        @php $isLast = $loop->last; @endphp
        <div id="proj-card-{{ $project->id }}" style="border-bottom:{{ $isLast ? 'none' : '1px solid #e2e8f0' }};">

            {{-- Project header --}}
            <div class="proj-header" style="background:#fafafa;border-bottom:1px solid #f1f5f9;">
                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                    <svg width="14" height="14" fill="none" stroke="{{ $project->is_active ? '#2563eb' : '#9ca3af' }}" viewBox="0 0 24 24" style="flex-shrink:0;" id="proj-icon-{{ $project->id }}">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    <span id="proj-name-{{ $project->id }}" style="font-size:13px;font-weight:600;color:#1e293b;">{{ $project->name }}</span>
                    <span id="proj-inactive-badge-{{ $project->id }}" class="badge-inactive" style="{{ $project->is_active ? 'display:none' : '' }}">Inactive</span>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <button type="button" onclick="openEditProject({{ $project->id }}, '{{ addslashes($project->name) }}', {{ $project->is_active ? 'true' : 'false' }})"
                        class="btn-secondary btn-sm">Edit</button>
                    <button type="button" onclick="deleteProject({{ $project->id }}, '{{ addslashes($project->name) }}')"
                        class="btn-danger btn-sm">Delete</button>
                </div>
            </div>

            {{-- Project edit strip --}}
            <div class="proj-edit-wrap" id="proj-edit-{{ $project->id }}">
                <input id="edit-proj-name-{{ $project->id }}" type="text" class="form-input" style="flex:1;font-size:13px;">
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;">
                    <input type="checkbox" id="edit-proj-active-{{ $project->id }}" {{ $project->is_active ? 'checked' : '' }} style="width:14px;height:14px;">
                    Active
                </label>
                <button type="button" onclick="saveProject({{ $project->id }})" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>
                <button type="button" onclick="closeEditProject({{ $project->id }})" class="btn-secondary btn-sm">Cancel</button>
                <p id="edit-proj-error-{{ $project->id }}" class="field-error" style="margin:0;"></p>
            </div>

            {{-- Project body: Locations + Departments --}}
            <div class="proj-body" id="proj-body-{{ $project->id }}">
            <div class="proj-body-inner">
                {{-- Locations --}}
                <div class="proj-section">
                    <div class="proj-section-header">
                        <span class="proj-section-title">Locations</span>
                        <button type="button" onclick="openLocModal(null, {{ $project->id }})" class="btn-primary" style="padding:3px 10px;font-size:11px;">+ Add</button>
                    </div>
                    <div id="loc-list-{{ $project->id }}">
                        @forelse($project->locations as $location)
                        <div id="loc-wrap-{{ $location->id }}">
                            <div class="loc-row" id="loc-row-{{ $location->id }}">
                                <div style="display:flex;align-items:flex-start;gap:8px;flex:1;min-width:0;">
                                    <svg width="13" height="13" fill="none" stroke="{{ $location->is_active ? '#22c55e' : '#9ca3af' }}" viewBox="0 0 24 24" id="loc-icon-{{ $location->id }}" style="flex-shrink:0;margin-top:3px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <div style="flex:1;min-width:0;">
                                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                            <span id="loc-name-{{ $location->id }}" style="font-size:13px;font-weight:600;color:#1e293b;">{{ $location->name }}</span>
                                            <span id="loc-inactive-{{ $location->id }}" class="badge-inactive" style="{{ $location->is_active ? 'display:none' : '' }}">Inactive</span>
                                        </div>
                                        <div id="loc-address-{{ $location->id }}" style="font-size:12px;color:#64748b;margin-top:2px;{{ $location->address ? '' : 'display:none;' }}">{{ $location->address }}</div>
                                        <div id="loc-gps-{{ $location->id }}" style="font-size:11px;color:#94a3b8;margin-top:1px;font-family:monospace;{{ ($location->latitude && $location->longitude) ? '' : 'display:none;' }}">
                                            @if($location->latitude && $location->longitude){{ number_format((float)$location->latitude,6) }}°,&nbsp;{{ number_format((float)$location->longitude,6) }}°@endif
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:4px;flex-shrink:0;margin-top:1px;">
                                    <button type="button" onclick="openLocModal({{ $location->id }}, {{ $project->id }})" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>
                                    <button type="button" onclick="deleteLoc({{ $project->id }}, {{ $location->id }}, '{{ addslashes($location->name) }}')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div id="loc-empty-{{ $project->id }}" style="padding:12px 1.25rem;color:#9ca3af;font-size:13px;">No locations yet.</div>
                        @endforelse
                    </div>
                </div>
                {{-- Departments --}}
                <div class="proj-section">
                    <div class="proj-section-header">
                        <span class="proj-section-title">Departments</span>
                        <button type="button" onclick="openAddDept({{ $project->id }})" class="btn-primary" style="padding:3px 10px;font-size:11px;">+ Add</button>
                    </div>
                    <div class="dept-edit-row" id="dept-add-row-{{ $project->id }}">
                        <input id="dept-add-name-{{ $project->id }}" type="text" class="form-input" style="flex:1;font-size:12px;" placeholder="Department name…"
                            onkeydown="if(event.key==='Enter') saveDeptAdd({{ $project->id }}); if(event.key==='Escape') closeAddDept({{ $project->id }})">
                        <button type="button" onclick="saveDeptAdd({{ $project->id }})" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>
                        <button type="button" onclick="closeAddDept({{ $project->id }})" class="btn-secondary btn-sm">✕</button>
                    </div>
                    <div id="dept-list-{{ $project->id }}">
                        @forelse($project->departments as $dept)
                        <div id="dept-wrap-{{ $dept->id }}">
                            <div class="dept-row" id="dept-row-{{ $dept->id }}">
                                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                                    <svg width="13" height="13" fill="none" stroke="{{ $dept->is_active ? '#06b6d4' : '#9ca3af' }}" viewBox="0 0 24 24" id="dept-icon-{{ $dept->id }}" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span id="dept-name-{{ $dept->id }}" style="font-size:13px;font-weight:600;color:#1e293b;">{{ $dept->name }}</span>
                                    <span id="dept-inactive-{{ $dept->id }}" class="badge-inactive" style="{{ $dept->is_active ? 'display:none' : '' }}">Inactive</span>
                                </div>
                                <div style="display:flex;gap:4px;flex-shrink:0;">
                                    <button type="button" onclick="openEditDept({{ $dept->id }}, {{ $project->id }}, '{{ addslashes($dept->name) }}', {{ $dept->is_active ? 'true' : 'false' }})" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>
                                    <button type="button" onclick="deleteDept({{ $project->id }}, {{ $dept->id }}, '{{ addslashes($dept->name) }}')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>
                                </div>
                            </div>
                            <div class="dept-edit-row" id="dept-edit-row-{{ $dept->id }}">
                                <input id="dept-edit-name-{{ $dept->id }}" type="text" class="form-input" style="flex:1;font-size:12px;"
                                    onkeydown="if(event.key==='Enter') saveDeptEdit({{ $dept->id }}, {{ $project->id }}); if(event.key==='Escape') closeEditDept({{ $dept->id }})">
                                <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;">
                                    <input type="checkbox" id="dept-edit-active-{{ $dept->id }}" {{ $dept->is_active ? 'checked' : '' }} style="width:13px;height:13px;">
                                    Active
                                </label>
                                <button type="button" onclick="saveDeptEdit({{ $dept->id }}, {{ $project->id }})" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>
                                <button type="button" onclick="closeEditDept({{ $dept->id }})" class="btn-secondary btn-sm">✕</button>
                            </div>
                        </div>
                        @empty
                        <div id="dept-empty-{{ $project->id }}" style="padding:12px 1.25rem;color:#9ca3af;font-size:13px;">No departments yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>{{-- end proj-body-inner --}}
            </div>{{-- end proj-body --}}
        </div>{{-- end proj-card --}}
        @empty
        <div id="no-proj-msg-{{ $company->id }}" style="padding:1.5rem;text-align:center;color:#9ca3af;font-size:13px;">
            No projects yet — click "+ Add Project" above.
        </div>
        @endforelse
    </div>{{-- end proj-list --}}
</div>{{-- end company-card --}}

@empty
<div class="card card-body" style="text-align:center;padding:3rem;color:#9ca3af;">
    No companies yet. Add your first company above.
</div>
@endforelse
</div>{{-- end companies-list --}}

{{-- ═══════════════ Location Map Modal ═══════════════ --}}
<div id="loc-modal-overlay" onclick="if(event.target===this)closeLocModal()">
    <div id="loc-modal-box">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem; border-bottom:1px solid #e2e8f0; flex-shrink:0;">
            <h2 id="loc-modal-title" style="font-size:16px; font-weight:700; color:#0f172a; margin:0;"></h2>
            <button type="button" onclick="closeLocModal()" style="background:none; border:none; cursor:pointer; color:#94a3b8; padding:4px; line-height:0;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Two-panel body --}}
        <div style="display:flex; flex:1; overflow:hidden; min-height:0;">

            {{-- Left: form fields --}}
            <div style="width:310px; flex-shrink:0; padding:1.25rem 1.25rem 1rem; overflow-y:auto; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; gap:14px;">

                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">
                        Location Name <span style="color:#ef4444;">*</span>
                    </label>
                    <input id="loc-modal-name" type="text" class="form-input" style="width:100%; font-size:13px;" placeholder="e.g. Main Warehouse">
                    <p id="loc-modal-name-error" class="field-error"></p>
                </div>

                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Address</label>
                    <div style="display:flex; gap:6px; align-items:stretch;">
                        <input id="loc-modal-address" type="text" class="form-input" style="flex:1; font-size:13px;" placeholder="Street, City, Country"
                            onkeydown="if(event.key==='Enter'){event.preventDefault();geocodeAddress();}">
                        <button type="button" onclick="geocodeAddress()" title="Search address on map"
                            style="flex-shrink:0; padding:0 11px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:6px; cursor:pointer; color:#475569; display:flex; align-items:center;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Latitude</label>
                        <input id="loc-modal-lat" type="number" step="any" class="form-input"
                            style="width:100%; font-size:12px; font-family:monospace;" placeholder="25.2048"
                            oninput="onLatLngInput()">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Longitude</label>
                        <input id="loc-modal-lng" type="number" step="any" class="form-input"
                            style="width:100%; font-size:12px; font-family:monospace;" placeholder="55.2708"
                            oninput="onLatLngInput()">
                    </div>
                </div>

                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:9px 11px;">
                    <p style="font-size:11px; color:#0369a1; margin:0; line-height:1.6;">
                        <strong>Map tips:</strong><br>
                        • Click anywhere on the map to place the pin<br>
                        • Drag the pin to fine-tune position<br>
                        • Type an address and press <strong>↵</strong> or click 🔍 to find it
                    </p>
                </div>

                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:#374151;">
                    <input type="checkbox" id="loc-modal-active" checked style="width:14px; height:14px;">
                    Active
                </label>

                <p id="loc-modal-error" class="field-error"></p>
            </div>

            {{-- Right: Leaflet map --}}
            <div style="flex:1; position:relative;">
                <div id="loc-map"></div>
                <div id="loc-map-hint" style="position:absolute; bottom:10px; left:50%; transform:translateX(-50%); background:rgba(15,23,42,0.72); color:white; font-size:11px; padding:4px 12px; border-radius:20px; pointer-events:none; white-space:nowrap; z-index:1000;">
                    Click map to place pin • Drag pin to adjust
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div style="display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:0.875rem 1.5rem; border-top:1px solid #e2e8f0; flex-shrink:0;">
            <button type="button" onclick="closeLocModal()" class="btn-secondary">Cancel</button>
            <button type="button" onclick="saveLocModal()" class="btn-primary">Save Location</button>
        </div>
    </div>
</div>
{{-- ═══════════════════════════════════════════════════ --}}

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLc=" crossorigin=""></script>
<script>
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
var BASE = '{{ url("settings/projects") }}';

// Location data store (keyed by loc ID) — avoids inline JSON in onclick attributes
var LOC_DATA  = {!! $allLocsJson !!};
var DEPT_DATA = {!! $allDeptsJson !!};

// ── Core fetch helper ────────────────────────────────────────────────────────
function api(url, method, data) {
    var opts = { method: method, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    return fetch(url, opts).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    });
}

function firstError(err) {
    if (err && err.errors) { var k = Object.keys(err.errors); if (k.length) return err.errors[k[0]][0]; }
    return err.message || 'Something went wrong.';
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Leaflet Map ───────────────────────────────────────────────────────────────
var locMap = null, locMarker = null;
var defaultCenter = [25.2048, 55.2708]; // UAE fallback; overridden by geolocation
var defaultZoom   = 11;

// Attempt to get user's position for a better default center
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
        defaultCenter = [pos.coords.latitude, pos.coords.longitude];
        defaultZoom   = 13;
        // If map is already open with no pin, re-center
        if (locMap && !locMarker) locMap.setView(defaultCenter, defaultZoom);
    }, null, { timeout: 6000 });
}

function initMap() {
    if (locMap) return;
    locMap = L.map('loc-map').setView(defaultCenter, defaultZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(locMap);

    locMap.on('click', function(e) {
        setMapPin(e.latlng.lat, e.latlng.lng);
        document.getElementById('loc-modal-lat').value = e.latlng.lat.toFixed(7);
        document.getElementById('loc-modal-lng').value = e.latlng.lng.toFixed(7);
    });
}

function setMapPin(lat, lng) {
    var latlng = L.latLng(lat, lng);
    if (locMarker) {
        locMarker.setLatLng(latlng);
    } else {
        locMarker = L.marker(latlng, { draggable: true }).addTo(locMap);
        locMarker.on('dragend', function(e) {
            var pos = e.target.getLatLng();
            document.getElementById('loc-modal-lat').value = pos.lat.toFixed(7);
            document.getElementById('loc-modal-lng').value = pos.lng.toFixed(7);
        });
    }
    locMap.setView(latlng, Math.max(locMap.getZoom(), 14));
}

function removeMapPin() {
    if (locMarker) { locMarker.remove(); locMarker = null; }
}

function onLatLngInput() {
    var lat = parseFloat(document.getElementById('loc-modal-lat').value);
    var lng = parseFloat(document.getElementById('loc-modal-lng').value);
    if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        setMapPin(lat, lng);
    }
}

function geocodeAddress() {
    var addr = document.getElementById('loc-modal-address').value.trim();
    if (!addr) { showToast('Enter an address to search.', 'warn'); return; }
    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr) + '&limit=1', {
        headers: { 'Accept-Language': 'en', 'User-Agent': 'OperationModule/1.0' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data && data.length) {
            var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
            document.getElementById('loc-modal-lat').value = lat.toFixed(7);
            document.getElementById('loc-modal-lng').value = lng.toFixed(7);
            setMapPin(lat, lng);
        } else {
            showToast('Address not found — try a more specific query.', 'warn');
        }
    })
    .catch(function() { showToast('Geocoding failed. Check your connection.', 'error'); });
}

// ── Location Modal ────────────────────────────────────────────────────────────
var _locMode = 'add', _locId = null, _projId = null;

function openLocModal(locId, projectId) {
    _locMode = locId ? 'edit' : 'add';
    _locId   = locId;
    _projId  = projectId;

    var projNameEl = document.getElementById('proj-name-' + projectId);
    var projName   = projNameEl ? projNameEl.textContent.trim() : '';
    document.getElementById('loc-modal-title').textContent =
        locId ? 'Edit Location' : 'Add Location' + (projName ? ' — ' + projName : '');

    var d = locId ? (LOC_DATA[locId] || {}) : {};
    document.getElementById('loc-modal-name').value    = d.name    || '';
    document.getElementById('loc-modal-address').value = d.address || '';
    document.getElementById('loc-modal-lat').value     = d.latitude  != null ? d.latitude  : '';
    document.getElementById('loc-modal-lng').value     = d.longitude != null ? d.longitude : '';
    document.getElementById('loc-modal-active').checked = d.is_active !== false;
    document.getElementById('loc-modal-name-error').style.display = 'none';
    document.getElementById('loc-modal-error').style.display      = 'none';

    document.getElementById('loc-modal-overlay').classList.add('open');

    // Init/refresh map after overlay becomes visible
    setTimeout(function() {
        initMap();
        locMap.invalidateSize();
        removeMapPin();
        if (d.latitude != null && d.longitude != null) {
            setMapPin(parseFloat(d.latitude), parseFloat(d.longitude));
        } else {
            locMap.setView(defaultCenter, defaultZoom);
        }
    }, 60);
}

function closeLocModal() {
    document.getElementById('loc-modal-overlay').classList.remove('open');
}

function saveLocModal() {
    var name     = document.getElementById('loc-modal-name').value.trim();
    var address  = document.getElementById('loc-modal-address').value.trim() || null;
    var latRaw   = document.getElementById('loc-modal-lat').value.trim();
    var lngRaw   = document.getElementById('loc-modal-lng').value.trim();
    var isActive = document.getElementById('loc-modal-active').checked;
    var nameErr  = document.getElementById('loc-modal-name-error');
    var genErr   = document.getElementById('loc-modal-error');
    nameErr.style.display = 'none';
    genErr.style.display  = 'none';

    if (!name) { nameErr.textContent = 'Location name is required.'; nameErr.style.display = 'block'; return; }

    var payload = {
        name:      name,
        address:   address,
        latitude:  latRaw  !== '' ? parseFloat(latRaw)  : null,
        longitude: lngRaw  !== '' ? parseFloat(lngRaw) : null,
        is_active: isActive ? 1 : 0
    };

    var url    = BASE + '/' + _projId + '/locations' + (_locMode === 'edit' ? '/' + _locId : '');
    var method = _locMode === 'edit' ? 'PATCH' : 'POST';

    api(url, method, payload)
        .then(function(data) {
            var loc = data.location;
            // Update global data store
            LOC_DATA[loc.id] = loc;
            closeLocModal();

            if (_locMode === 'add') {
                var emptyEl = document.getElementById('loc-empty-' + _projId);
                if (emptyEl) emptyEl.remove();
                var div = document.createElement('div');
                div.innerHTML = buildLocationRow(_projId, loc);
                insertLocInOrder(_projId, div.firstElementChild);
                updateLocCount(_projId);
                showToast('Location "' + esc(loc.name) + '" added.', 'success');
            } else {
                // Update display
                document.getElementById('loc-name-' + loc.id).textContent = loc.name;
                var badge = document.getElementById('loc-inactive-' + loc.id);
                if (badge) badge.style.display = loc.is_active ? 'none' : '';
                var icon = document.getElementById('loc-icon-' + loc.id);
                if (icon) icon.setAttribute('stroke', loc.is_active ? '#22c55e' : '#9ca3af');

                var addrEl = document.getElementById('loc-address-' + loc.id);
                if (addrEl) { addrEl.textContent = loc.address || ''; addrEl.style.display = loc.address ? '' : 'none'; }

                var gpsEl = document.getElementById('loc-gps-' + loc.id);
                if (gpsEl) {
                    if (loc.latitude != null && loc.longitude != null) {
                        gpsEl.textContent = parseFloat(loc.latitude).toFixed(6) + '°, ' + parseFloat(loc.longitude).toFixed(6) + '°';
                        gpsEl.style.display = '';
                    } else {
                        gpsEl.style.display = 'none';
                    }
                }
                showToast('Location updated.', 'success');
            }
        })
        .catch(function(e) { genErr.textContent = firstError(e); genErr.style.display = 'block'; });
}

// ── Company CRUD ──────────────────────────────────────────────────────────────
function addCompany() {
    var input = document.getElementById('new-company-input');
    var err   = document.getElementById('new-company-error');
    var name  = input.value.trim();
    err.style.display = 'none';
    if (!name) { err.textContent = 'Company name is required.'; err.style.display = 'block'; return; }
    api(BASE + '/companies', 'POST', { name: name })
        .then(function(data) {
            input.value = '';
            var c = data.company;
            var list = document.getElementById('companies-list');
            var emptyEl = list.querySelector('.card.card-body');
            if (emptyEl) emptyEl.remove();
            var div = document.createElement('div');
            div.innerHTML = buildCompanyCard(c);
            list.appendChild(div.firstElementChild);
            updateStat('stat-companies', 1);
            showToast('Company "' + esc(c.name) + '" added.', 'success');
        })
        .catch(function(e) { err.textContent = firstError(e); err.style.display = 'block'; });
}

function openEditCompany(id, name, isActive) {
    document.getElementById('company-edit-' + id).classList.add('open');
    document.getElementById('edit-company-name-' + id).value = name;
    document.getElementById('edit-company-active-' + id).checked = isActive;
    var errEl = document.getElementById('edit-company-error-' + id);
    if (errEl) errEl.style.display = 'none';
}
function closeEditCompany(id) { document.getElementById('company-edit-' + id).classList.remove('open'); }

function saveCompany(id) {
    var name     = document.getElementById('edit-company-name-' + id).value.trim();
    var isActive = document.getElementById('edit-company-active-' + id).checked;
    var errEl    = document.getElementById('edit-company-error-' + id);
    errEl.style.display = 'none';
    if (!name) { errEl.textContent = 'Name is required.'; errEl.style.display = 'block'; return; }
    api(BASE + '/companies/' + id, 'PATCH', { name: name, is_active: isActive ? 1 : 0 })
        .then(function(data) {
            var c = data.company;
            closeEditCompany(id);
            document.getElementById('company-name-' + id).textContent = c.name;
            var badge = document.getElementById('company-inactive-' + id);
            if (badge) badge.style.display = c.is_active ? 'none' : '';
            showToast('Company updated.', 'success');
        })
        .catch(function(e) { errEl.textContent = firstError(e); errEl.style.display = 'block'; });
}

function deleteCompany(id, name) {
    confirmAction('Delete Company', 'Delete "' + name + '" and all its projects?', function() {
        api(BASE + '/companies/' + id, 'DELETE')
            .then(function() {
                var card = document.getElementById('company-card-' + id);
                if (card) card.remove();
                var list = document.getElementById('companies-list');
                if (list && !list.querySelector('[id^="company-card-"]')) {
                    list.innerHTML = '<div class="card card-body" style="text-align:center;padding:3rem;color:#9ca3af;">No companies yet. Add your first company above.</div>';
                }
                updateStat('stat-companies', -1);
                showToast('Company "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

// ── Add Project under Company ─────────────────────────────────────────────────
function openAddProject(companyId) {
    var strip = document.getElementById('add-proj-strip-' + companyId);
    strip.classList.add('open');
    var input = document.getElementById('add-proj-name-' + companyId);
    input.value = '';
    input.focus();
    var errEl = document.getElementById('add-proj-error-' + companyId);
    if (errEl) errEl.style.display = 'none';
}
function closeAddProject(companyId) {
    document.getElementById('add-proj-strip-' + companyId).classList.remove('open');
}
function saveAddProject(companyId) {
    var input = document.getElementById('add-proj-name-' + companyId);
    var errEl = document.getElementById('add-proj-error-' + companyId);
    var name  = input.value.trim();
    errEl.style.display = 'none';
    if (!name) { errEl.textContent = 'Project name is required.'; errEl.style.display = 'block'; return; }
    api(BASE, 'POST', { name: name, company_id: companyId })
        .then(function(data) {
            input.value = '';
            closeAddProject(companyId);
            var p = data.project;
            var projList = document.getElementById('proj-list-' + companyId);
            var noMsg = document.getElementById('no-proj-msg-' + companyId);
            if (noMsg) noMsg.remove();
            var div = document.createElement('div');
            div.innerHTML = buildProjectCard(p, companyId);
            projList.appendChild(div.firstElementChild);
            updateCompanyProjCount(companyId);
            updateStat('stat-projects', 1);
            showToast('Project "' + esc(p.name) + '" added.', 'success');
        })
        .catch(function(e) { errEl.textContent = firstError(e); errEl.style.display = 'block'; });
}

// ── Project CRUD ──────────────────────────────────────────────────────────────
function openEditProject(id, name, isActive) {
    document.getElementById('proj-edit-' + id).classList.add('open');
    document.getElementById('edit-proj-name-' + id).value = name;
    document.getElementById('edit-proj-active-' + id).checked = isActive;
    var errEl = document.getElementById('edit-proj-error-' + id);
    if (errEl) errEl.style.display = 'none';
}
function closeEditProject(id) { document.getElementById('proj-edit-' + id).classList.remove('open'); }

function saveProject(id) {
    var name     = document.getElementById('edit-proj-name-' + id).value.trim();
    var isActive = document.getElementById('edit-proj-active-' + id).checked;
    var errEl    = document.getElementById('edit-proj-error-' + id);
    errEl.style.display = 'none';
    if (!name) { errEl.textContent = 'Name is required.'; errEl.style.display = 'block'; return; }
    api(BASE + '/' + id, 'PATCH', { name: name, is_active: isActive ? 1 : 0 })
        .then(function(data) {
            var p = data.project;
            closeEditProject(id);
            document.getElementById('proj-name-' + id).textContent = p.name;
            var badge = document.getElementById('proj-inactive-badge-' + id);
            if (badge) badge.style.display = p.is_active ? 'none' : '';
            var icon = document.getElementById('proj-icon-' + id);
            if (icon) icon.setAttribute('stroke', p.is_active ? '#2563eb' : '#9ca3af');
            showToast('Project updated.', 'success');
        })
        .catch(function(e) { errEl.textContent = firstError(e); errEl.style.display = 'block'; });
}

function deleteProject(id, name) {
    confirmAction('Delete Project', 'Delete "' + name + '" and all its locations and departments?', function() {
        api(BASE + '/' + id, 'DELETE')
            .then(function() {
                var card = document.getElementById('proj-card-' + id);
                if (card) {
                    var projList = card.closest('[id^="proj-list-"]');
                    card.remove();
                    if (projList) {
                        if (!projList.querySelector('[id^="proj-card-"]')) {
                            var coId = projList.id.replace('proj-list-', '');
                            projList.innerHTML = '<div id="no-proj-msg-' + coId + '" style="padding:1.5rem;text-align:center;color:#9ca3af;font-size:13px;">No projects yet — click &quot;+ Add Project&quot; above.</div>';
                        }
                        var coCard = projList.closest('[id^="company-card-"]');
                        if (coCard) updateCompanyProjCount(coCard.id.replace('company-card-', ''));
                    }
                }
                updateStat('stat-projects', -1);
                showToast('Project "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

// ── Stat & count helpers ──────────────────────────────────────────────────────
function updateStat(statId, delta) {
    var el = document.getElementById(statId);
    if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
}

function updateCompanyProjCount(companyId) {
    var projList = document.getElementById('proj-list-' + companyId);
    var count    = projList ? projList.querySelectorAll('[id^="proj-card-"]').length : 0;
    var el       = document.getElementById('company-proj-count-' + companyId);
    if (el) el.textContent = count + ' ' + (count === 1 ? 'project' : 'projects');
}

// ── JS Card Builders ──────────────────────────────────────────────────────────
function buildCompanyCard(c) {
    var cName   = esc(c.name);
    var rawName = (c.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    return '<div id="company-card-' + c.id + '" style="margin-bottom:1.25rem;">'
        + '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1.25rem;background:linear-gradient(135deg,#eef2ff,#e0e7ff);border:1px solid #c7d2fe;border-radius:0.875rem 0.875rem 0 0;border-bottom:none;">'
        +   '<div style="display:flex;align-items:center;gap:10px;">'
        +     '<svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>'
        +     '<span id="company-name-' + c.id + '" style="font-size:15px;font-weight:700;color:#3730a3;">' + cName + '</span>'
        +     '<span id="company-inactive-' + c.id + '" class="badge-inactive" style="display:none;">Inactive</span>'
        +     '<span id="company-proj-count-' + c.id + '" style="font-size:12px;color:#6366f1;opacity:.7;">0 projects</span>'
        +   '</div>'
        +   '<div style="display:flex;gap:6px;align-items:center;">'
        +     '<button type="button" onclick="openAddProject(' + c.id + ')" class="btn-primary" style="padding:4px 12px;font-size:12px;">+ Add Project</button>'
        +     '<button type="button" onclick="openEditCompany(' + c.id + ', \'' + rawName + '\', true)" class="btn-secondary btn-sm">Edit</button>'
        +     '<button type="button" onclick="deleteCompany(' + c.id + ', \'' + rawName + '\')" class="btn-danger btn-sm">Delete</button>'
        +   '</div>'
        + '</div>'
        + '<div class="proj-edit-wrap" id="company-edit-' + c.id + '" style="border-radius:0;border-top:1px solid #c7d2fe;border-left:1px solid #c7d2fe;border-right:1px solid #c7d2fe;">'
        +   '<input id="edit-company-name-' + c.id + '" type="text" class="form-input" style="flex:1;font-size:13px;">'
        +   '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;"><input type="checkbox" id="edit-company-active-' + c.id + '" checked style="width:14px;height:14px;"> Active</label>'
        +   '<button type="button" onclick="saveCompany(' + c.id + ')" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>'
        +   '<button type="button" onclick="closeEditCompany(' + c.id + ')" class="btn-secondary btn-sm">Cancel</button>'
        +   '<p id="edit-company-error-' + c.id + '" class="field-error" style="margin:0;"></p>'
        + '</div>'
        + '<div class="proj-edit-wrap" id="add-proj-strip-' + c.id + '" style="border-radius:0;border-left:1px solid #c7d2fe;border-right:1px solid #c7d2fe;background:#f0fdf4;border-color:#bbf7d0;">'
        +   '<input id="add-proj-name-' + c.id + '" type="text" class="form-input" style="flex:1;font-size:13px;" placeholder="New project name…" onkeydown="if(event.key===\'Enter\') saveAddProject(' + c.id + '); if(event.key===\'Escape\') closeAddProject(' + c.id + ')">'
        +   '<button type="button" onclick="saveAddProject(' + c.id + ')" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>'
        +   '<button type="button" onclick="closeAddProject(' + c.id + ')" class="btn-secondary btn-sm">Cancel</button>'
        +   '<p id="add-proj-error-' + c.id + '" class="field-error" style="margin:0;"></p>'
        + '</div>'
        + '<div id="proj-list-' + c.id + '" style="border:1px solid #c7d2fe;border-top:none;border-radius:0 0 0.875rem 0.875rem;overflow:hidden;">'
        +   '<div id="no-proj-msg-' + c.id + '" style="padding:1.5rem;text-align:center;color:#9ca3af;font-size:13px;">No projects yet — click &quot;+ Add Project&quot; above.</div>'
        + '</div>'
        + '</div>';
}

function buildProjectCard(p, companyId) {
    var pName   = esc(p.name);
    var rawName = (p.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    return '<div id="proj-card-' + p.id + '" style="border-bottom:1px solid #e2e8f0;">'
        + '<div class="proj-header" style="background:#fafafa;border-bottom:1px solid #f1f5f9;">'
        +   '<div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">'
        +     '<svg width="14" height="14" fill="none" stroke="#2563eb" viewBox="0 0 24 24" style="flex-shrink:0;" id="proj-icon-' + p.id + '"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>'
        +     '<span id="proj-name-' + p.id + '" style="font-size:13px;font-weight:600;color:#1e293b;">' + pName + '</span>'
        +     '<span id="proj-inactive-badge-' + p.id + '" class="badge-inactive" style="display:none;">Inactive</span>'
        +   '</div>'
        +   '<div style="display:flex;gap:6px;flex-shrink:0;">'
        +     '<button type="button" onclick="openEditProject(' + p.id + ', \'' + rawName + '\', true)" class="btn-secondary btn-sm">Edit</button>'
        +     '<button type="button" onclick="deleteProject(' + p.id + ', \'' + rawName + '\')" class="btn-danger btn-sm">Delete</button>'
        +   '</div>'
        + '</div>'
        + '<div class="proj-edit-wrap" id="proj-edit-' + p.id + '">'
        +   '<input id="edit-proj-name-' + p.id + '" type="text" class="form-input" style="flex:1;font-size:13px;">'
        +   '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;"><input type="checkbox" id="edit-proj-active-' + p.id + '" checked style="width:14px;height:14px;"> Active</label>'
        +   '<button type="button" onclick="saveProject(' + p.id + ')" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>'
        +   '<button type="button" onclick="closeEditProject(' + p.id + ')" class="btn-secondary btn-sm">Cancel</button>'
        +   '<p id="edit-proj-error-' + p.id + '" class="field-error" style="margin:0;"></p>'
        + '</div>'
        + '<div class="proj-body" id="proj-body-' + p.id + '">'
        +   '<div class="proj-body-inner">'
        +     '<div class="proj-section">'
        +       '<div class="proj-section-header"><span class="proj-section-title">Locations</span>'
        +       '<button type="button" onclick="openLocModal(null,' + p.id + ')" class="btn-primary" style="padding:3px 10px;font-size:11px;">+ Add</button></div>'
        +       '<div id="loc-list-' + p.id + '"><div id="loc-empty-' + p.id + '" style="padding:12px 1.25rem;color:#9ca3af;font-size:13px;">No locations yet.</div></div>'
        +     '</div>'
        +     '<div class="proj-section">'
        +       '<div class="proj-section-header"><span class="proj-section-title">Departments</span>'
        +       '<button type="button" onclick="openAddDept(' + p.id + ')" class="btn-primary" style="padding:3px 10px;font-size:11px;">+ Add</button></div>'
        +       '<div class="dept-edit-row" id="dept-add-row-' + p.id + '">'
        +         '<input id="dept-add-name-' + p.id + '" type="text" class="form-input" style="flex:1;font-size:12px;" placeholder="Department name…" onkeydown="if(event.key===\'Enter\') saveDeptAdd(' + p.id + '); if(event.key===\'Escape\') closeAddDept(' + p.id + ')">'
        +         '<button type="button" onclick="saveDeptAdd(' + p.id + ')" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>'
        +         '<button type="button" onclick="closeAddDept(' + p.id + ')" class="btn-secondary btn-sm">✕</button>'
        +       '</div>'
        +       '<div id="dept-list-' + p.id + '"><div id="dept-empty-' + p.id + '" style="padding:12px 1.25rem;color:#9ca3af;font-size:13px;">No departments yet.</div></div>'
        +     '</div>'
        +   '</div>'
        + '</div>'
        + '</div>';
}

// ── Location Delete ───────────────────────────────────────────────────────────
function deleteLoc(projectId, locId, name) {
    confirmAction('Delete Location', 'Delete "' + name + '"?', function() {
        api(BASE + '/' + projectId + '/locations/' + locId, 'DELETE')
            .then(function() {
                delete LOC_DATA[locId];
                var wrap = document.getElementById('loc-wrap-' + locId);
                if (wrap) wrap.remove();
                updateLocCount(projectId);
                var list = document.getElementById('loc-list-' + projectId);
                if (list && !list.querySelector('[id^="loc-wrap-"]')) {
                    list.innerHTML = '<div id="loc-empty-' + projectId + '" style="padding:14px 1.25rem 14px 2.5rem; color:#9ca3af; font-size:13px; border-bottom:1px solid #f1f5f9;">No locations yet — add the first one below.</div>';
                }
                showToast('Location "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function updateLocCount(projectId) {
    var list  = document.getElementById('loc-list-' + projectId);
    var count = list ? list.querySelectorAll('[id^="loc-wrap-"]').length : 0;
    var el    = document.getElementById('proj-loc-count-' + projectId);
    if (el) el.textContent = count + ' ' + (count === 1 ? 'location' : 'locations');
}

function insertLocInOrder(projectId, newWrapEl) {
    var list    = document.getElementById('loc-list-' + projectId);
    var newName = (newWrapEl.querySelector('[id^="loc-name-"]').textContent || '').trim().toLowerCase();
    var wraps   = list.querySelectorAll('[id^="loc-wrap-"]');
    var inserted = false;
    for (var i = 0; i < wraps.length; i++) {
        var nameEl = wraps[i].querySelector('[id^="loc-name-"]');
        if (nameEl && newName < nameEl.textContent.trim().toLowerCase()) {
            list.insertBefore(newWrapEl, wraps[i]);
            inserted = true;
            break;
        }
    }
    if (!inserted) list.appendChild(newWrapEl);
}

// ── Department CRUD ───────────────────────────────────────────────────────────
function openAddDept(projectId) {
    var row = document.getElementById('dept-add-row-' + projectId);
    row.classList.add('open');
    document.getElementById('dept-add-name-' + projectId).value = '';
    document.getElementById('dept-add-name-' + projectId).focus();
}
function closeAddDept(projectId) {
    document.getElementById('dept-add-row-' + projectId).classList.remove('open');
}
function saveDeptAdd(projectId) {
    var input = document.getElementById('dept-add-name-' + projectId);
    var name  = input.value.trim();
    if (!name) { showToast('Department name is required.', 'warn'); return; }
    api(BASE + '/' + projectId + '/departments', 'POST', { name: name })
        .then(function(data) {
            var dept = data.department;
            DEPT_DATA[dept.id] = dept;
            closeAddDept(projectId);
            var emptyEl = document.getElementById('dept-empty-' + projectId);
            if (emptyEl) emptyEl.remove();
            var list = document.getElementById('dept-list-' + projectId);
            var div  = document.createElement('div');
            div.innerHTML = buildDeptRow(projectId, dept);
            insertDeptInOrder(projectId, div.firstElementChild);
            updateDeptCount(projectId);
            showToast('Department "' + esc(dept.name) + '" added.', 'success');
        })
        .catch(function(e) { showToast(firstError(e), 'error'); });
}

function openEditDept(deptId, projectId, name, isActive) {
    document.getElementById('dept-row-' + deptId).style.display = 'none';
    var row = document.getElementById('dept-edit-row-' + deptId);
    row.classList.add('open');
    document.getElementById('dept-edit-name-' + deptId).value   = name;
    document.getElementById('dept-edit-active-' + deptId).checked = isActive;
    document.getElementById('dept-edit-name-' + deptId).focus();
}
function closeEditDept(deptId) {
    document.getElementById('dept-edit-row-' + deptId).classList.remove('open');
    document.getElementById('dept-row-' + deptId).style.display = '';
}
function saveDeptEdit(deptId, projectId) {
    var name     = document.getElementById('dept-edit-name-' + deptId).value.trim();
    var isActive = document.getElementById('dept-edit-active-' + deptId).checked;
    if (!name) { showToast('Department name is required.', 'warn'); return; }
    api(BASE + '/' + projectId + '/departments/' + deptId, 'PATCH', { name: name, is_active: isActive ? 1 : 0 })
        .then(function(data) {
            var dept = data.department;
            DEPT_DATA[dept.id] = dept;
            closeEditDept(deptId);
            document.getElementById('dept-name-' + deptId).textContent = dept.name;
            var badge = document.getElementById('dept-inactive-' + deptId);
            if (badge) badge.style.display = dept.is_active ? 'none' : '';
            var icon = document.getElementById('dept-icon-' + deptId);
            if (icon) icon.setAttribute('stroke', dept.is_active ? '#06b6d4' : '#9ca3af');
            showToast('Department updated.', 'success');
        })
        .catch(function(e) { showToast(firstError(e), 'error'); });
}
function deleteDept(projectId, deptId, name) {
    confirmAction('Delete Department', 'Delete "' + name + '"?', function() {
        api(BASE + '/' + projectId + '/departments/' + deptId, 'DELETE')
            .then(function() {
                delete DEPT_DATA[deptId];
                var wrap = document.getElementById('dept-wrap-' + deptId);
                if (wrap) wrap.remove();
                updateDeptCount(projectId);
                var list = document.getElementById('dept-list-' + projectId);
                if (list && !list.querySelector('[id^="dept-wrap-"]')) {
                    list.innerHTML = '<div id="dept-empty-' + projectId + '" style="padding:14px 1.25rem;color:#9ca3af;font-size:13px;">No departments yet.</div>';
                }
                showToast('Department "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

function updateDeptCount(projectId) {
    var list  = document.getElementById('dept-list-' + projectId);
    var count = list ? list.querySelectorAll('[id^="dept-wrap-"]').length : 0;
    var el    = document.getElementById('proj-dept-count-' + projectId);
    if (el) el.textContent = count + ' ' + (count === 1 ? 'dept' : 'depts');
}

function insertDeptInOrder(projectId, newWrapEl) {
    var list    = document.getElementById('dept-list-' + projectId);
    var newName = (newWrapEl.querySelector('[id^="dept-name-"]').textContent || '').trim().toLowerCase();
    var wraps   = list.querySelectorAll('[id^="dept-wrap-"]');
    var inserted = false;
    for (var i = 0; i < wraps.length; i++) {
        var nameEl = wraps[i].querySelector('[id^="dept-name-"]');
        if (nameEl && newName < nameEl.textContent.trim().toLowerCase()) {
            list.insertBefore(newWrapEl, wraps[i]);
            inserted = true; break;
        }
    }
    if (!inserted) list.appendChild(newWrapEl);
}

function buildDeptRow(projectId, dept) {
    var safeName = (dept.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    return '<div id="dept-wrap-' + dept.id + '">'
        + '<div class="dept-row" id="dept-row-' + dept.id + '">'
        +   '<div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">'
        +     '<svg width="13" height="13" fill="none" stroke="#06b6d4" viewBox="0 0 24 24" id="dept-icon-' + dept.id + '" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
        +     '<span id="dept-name-' + dept.id + '" style="font-size:13px;font-weight:600;color:#1e293b;">' + esc(dept.name) + '</span>'
        +     '<span id="dept-inactive-' + dept.id + '" class="badge-inactive" style="display:none;">Inactive</span>'
        +   '</div>'
        +   '<div style="display:flex;gap:4px;flex-shrink:0;">'
        +     '<button type="button" onclick="openEditDept(' + dept.id + ',' + projectId + ',\'' + safeName + '\',true)" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>'
        +     '<button type="button" onclick="deleteDept(' + projectId + ',' + dept.id + ',\'' + safeName + '\')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>'
        +   '</div>'
        + '</div>'
        + '<div class="dept-edit-row" id="dept-edit-row-' + dept.id + '">'
        +   '<input id="dept-edit-name-' + dept.id + '" type="text" class="form-input" style="flex:1;font-size:12px;" onkeydown="if(event.key===\'Enter\') saveDeptEdit(' + dept.id + ',' + projectId + '); if(event.key===\'Escape\') closeEditDept(' + dept.id + ')">'
        +   '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;"><input type="checkbox" id="dept-edit-active-' + dept.id + '" checked style="width:13px;height:13px;"> Active</label>'
        +   '<button type="button" onclick="saveDeptEdit(' + dept.id + ',' + projectId + ')" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>'
        +   '<button type="button" onclick="closeEditDept(' + dept.id + ')" class="btn-secondary btn-sm">✕</button>'
        + '</div>'
        + '</div>';
}

function buildLocationRow(projectId, loc) {
    var safeName = (loc.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    var addrHtml = loc.address
        ? '<div id="loc-address-' + loc.id + '" style="font-size:12px;color:#64748b;margin-top:2px;">' + esc(loc.address) + '</div>'
        : '<div id="loc-address-' + loc.id + '" style="font-size:12px;color:#64748b;margin-top:2px;display:none;"></div>';
    var gpsHtml = (loc.latitude != null && loc.longitude != null)
        ? '<div id="loc-gps-' + loc.id + '" style="font-size:11px;color:#94a3b8;margin-top:1px;font-family:monospace;">' + parseFloat(loc.latitude).toFixed(6) + '°, ' + parseFloat(loc.longitude).toFixed(6) + '°</div>'
        : '<div id="loc-gps-' + loc.id + '" style="font-size:11px;color:#94a3b8;margin-top:1px;font-family:monospace;display:none;"></div>';
    return '<div id="loc-wrap-' + loc.id + '">'
        + '<div class="loc-row" id="loc-row-' + loc.id + '">'
        +   '<div style="display:flex;align-items:flex-start;gap:10px;flex:1;min-width:0;">'
        +     '<svg width="14" height="14" fill="none" stroke="#22c55e" viewBox="0 0 24 24" id="loc-icon-' + loc.id + '" style="flex-shrink:0;margin-top:3px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
        +     '<div style="flex:1;min-width:0;">'
        +       '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span id="loc-name-' + loc.id + '" style="font-size:13px;font-weight:600;color:#1e293b;">' + esc(loc.name) + '</span><span id="loc-inactive-' + loc.id + '" class="badge-inactive" style="display:none;">Inactive</span></div>'
        +       addrHtml + gpsHtml
        +     '</div>'
        +   '</div>'
        +   '<div style="display:flex;gap:4px;flex-shrink:0;margin-top:1px;">'
        +     '<button type="button" onclick="openLocModal(' + loc.id + ', ' + projectId + ')" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>'
        +     '<button type="button" onclick="deleteLoc(' + projectId + ', ' + loc.id + ', \'' + safeName + '\')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>'
        +   '</div>'
        + '</div>'
        + '</div>';
}

</script>
@endsection
