@extends('layouts.app')

@section('title', 'Settings — Projects')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
    .proj-card { border:1px solid #e2e8f0; border-radius:0.875rem; overflow:hidden; margin-bottom:1rem; background:white; }
    .loc-row { display:flex; align-items:flex-start; justify-content:space-between; padding:0.65rem 1rem 0.65rem 1.25rem; border-bottom:1px solid #f1f5f9; }
    .proj-edit-strip { display:none; padding:0.65rem 1.25rem; background:#f0f9ff; border-bottom:1px solid #bae6fd; align-items:center; gap:8px; }
    .proj-edit-strip.open { display:flex; }
    .badge-inactive { display:inline-block; padding:1px 7px; border-radius:9px; font-size:11px; font-weight:600; background:#fee2e2; color:#dc2626; margin-left:6px; }
    .field-error { color:#dc2626; font-size:12px; margin-top:4px; display:none; }
    .stat-card { background:white; border:1px solid #e2e8f0; border-radius:0.75rem; padding:1.25rem 1.5rem; }
    #loc-modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:9999; align-items:center; justify-content:center; }
    #loc-modal-overlay.open { display:flex; }
    #loc-modal-box { background:white; border-radius:1rem; width:940px; max-width:96vw; max-height:93vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 25px 60px rgba(0,0,0,0.25); }
    #loc-map { height:100%; width:100%; min-height:420px; }
</style>

{{-- Page header --}}
<div class="mb-5" style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">Projects</h1>
        <p class="page-subtitle">Manage projects and their locations.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
        <a href="{{ route('settings.projects.template') }}" class="btn-secondary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Template
        </a>
        <button type="button" onclick="openImportModal()" class="btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/></svg>
            Import Excel
        </button>
        <button type="button" onclick="openAddProjectModal()" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Project
        </button>
    </div>
</div>

{{-- Stat boxes --}}
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px;">
    <div class="stat-card" style="border-top:3px solid #3b82f6;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#3b82f6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            </div>
            <div>
                <div id="stat-projects" style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_projects'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Total Projects</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #22c55e;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#22c55e" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div id="stat-active" style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['active_projects'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Active</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #8b5cf6;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#f5f3ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#8b5cf6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div id="stat-locations" style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_locations'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Locations</div>
            </div>
        </div>
    </div>
    <div class="stat-card" style="border-top:3px solid #6366f1;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#eef2ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <div style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_companies'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Companies</div>
            </div>
        </div>
    </div>
</div>

{{-- Projects list --}}
@php
$allLocsData = [];
foreach ($projects as $proj) {
    foreach ($proj->locations as $loc) {
        $allLocsData[$loc->id] = [
            'id' => $loc->id, 'name' => $loc->name,
            'address' => $loc->address,
            'latitude' => $loc->latitude, 'longitude' => $loc->longitude,
            'is_active' => $loc->is_active,
        ];
    }
}
@endphp

<div id="projects-list">
@forelse($projects as $project)
@php $company = $companies->firstWhere('id', $project->company_id); @endphp

<div id="proj-card-{{ $project->id }}" class="proj-card">

    {{-- Project header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;background:linear-gradient(135deg,#eff6ff,#dbeafe);">
        <div style="display:flex;align-items:center;gap:10px;">
            <svg width="16" height="16" fill="none" stroke="{{ $project->is_active ? '#2563eb' : '#9ca3af' }}" viewBox="0 0 24 24" style="flex-shrink:0;" id="proj-icon-{{ $project->id }}">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            <span id="proj-name-{{ $project->id }}" style="font-size:15px;font-weight:700;color:#1e40af;">{{ $project->name }}</span>
            <span id="proj-inactive-{{ $project->id }}" class="badge-inactive" style="{{ $project->is_active ? 'display:none' : '' }}">Inactive</span>
            @if($company)
            <span id="proj-company-{{ $project->id }}" style="font-size:12px;color:#3b82f6;opacity:.8;background:#dbeafe;padding:1px 8px;border-radius:9px;">{{ $company->name }}</span>
            @else
            <span id="proj-company-{{ $project->id }}" style="font-size:12px;color:#3b82f6;opacity:.8;background:#dbeafe;padding:1px 8px;border-radius:9px;display:none;"></span>
            @endif
            <span id="proj-loc-count-{{ $project->id }}" style="font-size:12px;color:#6366f1;opacity:.75;">{{ $project->locations->count() }} {{ Str::plural('location', $project->locations->count()) }}</span>
        </div>
        <div style="display:flex;gap:6px;align-items:center;">
            <button type="button" onclick="openAddLocModal({{ $project->id }})"
                class="btn-secondary btn-sm" style="border-color:#93c5fd;color:#1d4ed8;">+ Location</button>
            <button type="button" onclick="openEditProject({{ $project->id }}, '{{ addslashes($project->name) }}', {{ $project->company_id ?? 'null' }}, {{ $project->is_active ? 'true' : 'false' }})"
                class="btn-secondary btn-sm">Edit</button>
            <button type="button" onclick="deleteProject({{ $project->id }}, '{{ addslashes($project->name) }}')"
                class="btn-danger btn-sm">Delete</button>
        </div>
    </div>

    {{-- Edit strip --}}
    <div class="proj-edit-strip" id="proj-edit-{{ $project->id }}">
        <input id="edit-proj-name-{{ $project->id }}" type="text" class="form-input" style="flex:1;font-size:13px;"
            onkeydown="if(event.key==='Enter') saveProject({{ $project->id }}); if(event.key==='Escape') closeEditProject({{ $project->id }})">
        <select id="edit-proj-company-{{ $project->id }}" class="form-input" style="font-size:13px;width:auto;min-width:140px;">
            <option value="">— No company —</option>
            @foreach($companies as $c)
            <option value="{{ $c->id }}" {{ $project->company_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;">
            <input type="checkbox" id="edit-proj-active-{{ $project->id }}" {{ $project->is_active ? 'checked' : '' }} style="width:14px;height:14px;">
            Active
        </label>
        <button type="button" onclick="saveProject({{ $project->id }})" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>
        <button type="button" onclick="closeEditProject({{ $project->id }})" class="btn-secondary btn-sm">Cancel</button>
        <p id="edit-proj-error-{{ $project->id }}" class="field-error" style="margin:0;"></p>
    </div>

    {{-- Locations list --}}
    <div id="loc-list-{{ $project->id }}">
        @forelse($project->locations as $loc)
        <div id="loc-wrap-{{ $loc->id }}">
            <div class="loc-row" id="loc-row-{{ $loc->id }}">
                <div style="display:flex;align-items:flex-start;gap:8px;flex:1;min-width:0;">
                    <svg width="13" height="13" fill="none" stroke="{{ $loc->is_active ? '#22c55e' : '#9ca3af' }}" viewBox="0 0 24 24" id="loc-icon-{{ $loc->id }}" style="flex-shrink:0;margin-top:3px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <span id="loc-name-{{ $loc->id }}" style="font-size:13px;font-weight:600;color:#1e293b;">{{ $loc->name }}</span>
                            <span id="loc-inactive-{{ $loc->id }}" class="badge-inactive" style="{{ $loc->is_active ? 'display:none' : '' }}">Inactive</span>
                        </div>
                        <div id="loc-address-{{ $loc->id }}" style="font-size:12px;color:#64748b;margin-top:2px;{{ $loc->address ? '' : 'display:none' }}">{{ $loc->address }}</div>
                        <div id="loc-gps-{{ $loc->id }}" style="font-size:11px;color:#94a3b8;margin-top:1px;font-family:monospace;{{ ($loc->latitude && $loc->longitude) ? '' : 'display:none' }}">
                            @if($loc->latitude && $loc->longitude){{ number_format((float)$loc->latitude,6) }}°,&nbsp;{{ number_format((float)$loc->longitude,6) }}°@endif
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:4px;flex-shrink:0;margin-top:1px;">
                    <button type="button" onclick="openEditLocModal({{ $loc->id }}, {{ $project->id }})" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>
                    <button type="button" onclick="deleteLoc({{ $project->id }}, {{ $loc->id }}, '{{ addslashes($loc->name) }}')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>
                </div>
            </div>
        </div>
        @empty
        <div id="loc-empty-{{ $project->id }}" style="padding:14px 1.25rem;color:#9ca3af;font-size:13px;">No locations yet — click "+ Location" to add one.</div>
        @endforelse
    </div>

</div>{{-- end proj-card --}}

@empty
<div id="projects-empty" class="card card-body" style="text-align:center;padding:3rem;color:#9ca3af;">
    No projects yet. Click "Add Project" to create one.
</div>
@endforelse
</div>

{{-- ══ Import Modal ══ --}}
<div id="import-modal-overlay" onclick="if(event.target===this)closeImportModal()"
     style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:9998;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:1rem;width:520px;max-width:96vw;box-shadow:0 25px 60px rgba(0,0,0,0.22);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e2e8f0;">
            <div>
                <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">Import from Excel</h2>
                <p style="font-size:12px;color:#64748b;margin:3px 0 0;">Companies, projects and departments from .xlsx / .xls</p>
            </div>
            <button type="button" onclick="closeImportModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px;line-height:0;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div style="padding:1.25rem 1.5rem;">
            <label for="import-file-input" id="import-dz"
                 ondragover="event.preventDefault();this.classList.add('dz-active')"
                 ondragleave="this.classList.remove('dz-active')"
                 ondrop="importHandleDrop(event)"
                 style="display:block;border:2px dashed #cbd5e1;border-radius:0.75rem;padding:2rem;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;background:#f8fafc;">
                <svg id="import-dz-icon" width="32" height="32" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/></svg>
                <p id="import-dz-text" style="font-size:14px;color:#475569;margin:0;font-weight:500;">Click to browse, or drop your Excel file here</p>
                <p id="import-dz-sub" style="font-size:12px;color:#94a3b8;margin:4px 0 0;">.xlsx or .xls — max 10 MB</p>
            </label>
            <input type="file" id="import-file-input" accept=".xlsx,.xls" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;" onchange="importFileSelected(this)">
            <div style="margin-top:1rem;background:#f0f9ff;border:1px solid #bae6fd;border-radius:0.5rem;padding:0.75rem 1rem;">
                <p style="font-size:12px;color:#0369a1;margin:0;line-height:1.7;">
                    <strong>Expected format:</strong><br>
                    <strong>Projects</strong> tab — <em>Company Name</em> | <em>Project Name</em><br>
                    Company is created automatically if it doesn't exist. Duplicate project names are skipped.
                </p>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.5rem;border-top:1px solid #e2e8f0;background:#f8fafc;">
            <a href="{{ route('settings.projects.template') }}" style="font-size:13px;color:#3b82f6;text-decoration:none;display:flex;align-items:center;gap:5px;">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download template
            </a>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="closeImportModal()" class="btn-secondary">Cancel</button>
                <button type="button" id="import-submit-btn" onclick="submitImport()" class="btn-primary" disabled
                        style="min-width:100px;opacity:.5;cursor:not-allowed;">Import</button>
            </div>
        </div>
    </div>
</div>
<style>
    #import-dz.dz-active  { border-color:#6366f1; background:#eef2ff; }
    #import-dz.dz-has-file { border-color:#22c55e; background:#f0fdf4; border-style:solid; }
</style>

{{-- ══ Add Project Modal ══ --}}
<div id="add-proj-overlay" onclick="if(event.target===this)closeAddProjectModal()"
     style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:9998;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:1rem;width:440px;max-width:96vw;box-shadow:0 25px 60px rgba(0,0,0,0.22);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e2e8f0;">
            <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">New Project</h2>
            <button type="button" onclick="closeAddProjectModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px;line-height:0;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:14px;">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Project Name <span style="color:#ef4444;">*</span></label>
                <input id="new-proj-name" type="text" class="form-input" style="width:100%;" placeholder="e.g. New Warehouse"
                    onkeydown="if(event.key==='Enter') addProject(); if(event.key==='Escape') closeAddProjectModal()">
                <p id="new-proj-name-error" class="field-error"></p>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Company</label>
                <select id="new-proj-company" class="form-input" style="width:100%;">
                    <option value="">— No company —</option>
                    @foreach($companies as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:0.875rem 1.5rem;border-top:1px solid #e2e8f0;background:#f8fafc;">
            <button type="button" onclick="closeAddProjectModal()" class="btn-secondary">Cancel</button>
            <button type="button" onclick="addProject()" class="btn-primary">Save Project</button>
        </div>
    </div>
</div>

{{-- ══ Location Map Modal ══ --}}
<div id="loc-modal-overlay" onclick="if(event.target===this)closeLocModal()">
    <div id="loc-modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
            <h2 id="loc-modal-title" style="font-size:16px;font-weight:700;color:#0f172a;margin:0;"></h2>
            <button type="button" onclick="closeLocModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px;line-height:0;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div style="display:flex;flex:1;overflow:hidden;min-height:0;">
            <div style="width:310px;flex-shrink:0;padding:1.25rem 1.25rem 1rem;overflow-y:auto;border-right:1px solid #e2e8f0;display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Location Name <span style="color:#ef4444;">*</span></label>
                    <input id="loc-modal-name" type="text" class="form-input" style="width:100%;font-size:13px;" placeholder="e.g. Main Warehouse">
                    <p id="loc-modal-name-error" class="field-error"></p>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Address</label>
                    <div style="display:flex;gap:6px;align-items:stretch;">
                        <input id="loc-modal-address" type="text" class="form-input" style="flex:1;font-size:13px;" placeholder="Street, City, Country"
                            onkeydown="if(event.key==='Enter'){event.preventDefault();geocodeAddress();}">
                        <button type="button" onclick="geocodeAddress()" title="Search address on map"
                            style="flex-shrink:0;padding:0 11px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;color:#475569;display:flex;align-items:center;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Latitude</label>
                        <input id="loc-modal-lat" type="number" step="any" class="form-input" style="width:100%;font-size:12px;font-family:monospace;" placeholder="25.2048" oninput="onLatLngInput()">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Longitude</label>
                        <input id="loc-modal-lng" type="number" step="any" class="form-input" style="width:100%;font-size:12px;font-family:monospace;" placeholder="55.2708" oninput="onLatLngInput()">
                    </div>
                </div>
                <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:9px 11px;">
                    <p style="font-size:11px;color:#0369a1;margin:0;line-height:1.6;">
                        <strong>Map tips:</strong><br>
                        • Click anywhere on the map to place the pin<br>
                        • Drag the pin to fine-tune position<br>
                        • Type an address and press <strong>↵</strong> or click 🔍 to find it
                    </p>
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#374151;">
                    <input type="checkbox" id="loc-modal-active" checked style="width:14px;height:14px;">
                    Active
                </label>
                <p id="loc-modal-error" class="field-error"></p>
            </div>
            <div style="flex:1;position:relative;">
                <div id="loc-map"></div>
                <div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);background:rgba(15,23,42,0.72);color:white;font-size:11px;padding:4px 12px;border-radius:20px;pointer-events:none;white-space:nowrap;z-index:1000;">
                    Click map to place pin • Drag pin to adjust
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:0.875rem 1.5rem;border-top:1px solid #e2e8f0;flex-shrink:0;">
            <button type="button" onclick="closeLocModal()" class="btn-secondary">Cancel</button>
            <button type="button" onclick="saveLocModal()" class="btn-primary">Save Location</button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLc=" crossorigin=""></script>
<script>
var CSRF       = document.querySelector('meta[name="csrf-token"]').content;
var BASE       = '{{ url("settings/projects") }}';
var IMPORT_URL = '{{ route("settings.projects.import") }}';
var COMPANIES  = {!! json_encode($companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()) !!};
var LOC_DATA   = {!! json_encode($allLocsData) !!};

// ── Import Modal ──────────────────────────────────────────────────────────────
var importFile = null;

function openImportModal() {
    importFile = null;
    document.getElementById('import-file-input').value = '';
    resetImportDz();
    document.getElementById('import-modal-overlay').style.display = 'flex';
}
function closeImportModal() { document.getElementById('import-modal-overlay').style.display = 'none'; }

function resetImportDz() {
    var dz  = document.getElementById('import-dz');
    var btn = document.getElementById('import-submit-btn');
    dz.classList.remove('dz-has-file', 'dz-active');
    document.getElementById('import-dz-text').textContent = 'Click to browse, or drop your Excel file here';
    document.getElementById('import-dz-sub').textContent  = '.xlsx or .xls — max 10 MB';
    document.getElementById('import-dz-icon').setAttribute('stroke', '#94a3b8');
    btn.disabled = true; btn.style.opacity = '.5'; btn.style.cursor = 'not-allowed';
}
function applyImportFile(file) {
    if (!file) return;
    importFile = file;
    var dz  = document.getElementById('import-dz');
    var btn = document.getElementById('import-submit-btn');
    dz.classList.remove('dz-active'); dz.classList.add('dz-has-file');
    document.getElementById('import-dz-text').textContent = file.name;
    document.getElementById('import-dz-sub').textContent  = (file.size / 1024).toFixed(1) + ' KB — ready to import';
    document.getElementById('import-dz-icon').setAttribute('stroke', '#22c55e');
    btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer';
}
function importFileSelected(input) { if (input.files && input.files[0]) applyImportFile(input.files[0]); }
function importHandleDrop(event) {
    event.preventDefault();
    document.getElementById('import-dz').classList.remove('dz-active');
    var file = event.dataTransfer.files[0];
    if (file) applyImportFile(file);
}
function submitImport() {
    if (!importFile) return;
    var btn = document.getElementById('import-submit-btn');
    btn.textContent = 'Importing…'; btn.disabled = true; btn.style.opacity = '.7';
    var formData = new FormData();
    formData.append('file', importFile);
    formData.append('_token', CSRF);
    fetch(IMPORT_URL, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
        .then(function(r) { return r.json().then(function(body) { return { ok: r.ok, body: body }; }); })
        .then(function(res) {
            btn.textContent = 'Import'; btn.disabled = false; btn.style.opacity = '1';
            if (res.ok && res.body.success) {
                closeImportModal();
                showToast(res.body.message, 'success');
                setTimeout(function() { window.location.reload(); }, 1200);
            } else { showToast(res.body.message || 'Import failed.', 'error'); }
        })
        .catch(function() {
            btn.textContent = 'Import'; btn.disabled = false; btn.style.opacity = '1';
            showToast('Upload failed. Check your connection.', 'error');
        });
}

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

// ── Add Project Modal ─────────────────────────────────────────────────────────
function openAddProjectModal() {
    document.getElementById('new-proj-name').value = '';
    document.getElementById('new-proj-name-error').style.display = 'none';
    document.getElementById('new-proj-company').value = '';
    document.getElementById('add-proj-overlay').style.display = 'flex';
    setTimeout(function() { document.getElementById('new-proj-name').focus(); }, 50);
}
function closeAddProjectModal() {
    document.getElementById('add-proj-overlay').style.display = 'none';
}
function addProject() {
    var name      = document.getElementById('new-proj-name').value.trim();
    var companyId = document.getElementById('new-proj-company').value || null;
    var nameErr   = document.getElementById('new-proj-name-error');
    nameErr.style.display = 'none';
    if (!name) { nameErr.textContent = 'Project name is required.'; nameErr.style.display = 'block'; return; }
    api(BASE, 'POST', { name: name, company_id: companyId })
        .then(function(data) {
            closeAddProjectModal();
            var p = data.project;
            var emptyEl = document.getElementById('projects-empty');
            if (emptyEl) emptyEl.remove();
            var list = document.getElementById('projects-list');
            var div  = document.createElement('div');
            div.innerHTML = buildProjectCard(p);
            list.appendChild(div.firstElementChild);
            updateStat('stat-projects', 1);
            if (p.is_active) updateStat('stat-active', 1);
            showToast('Project "' + esc(p.name) + '" added.', 'success');
        })
        .catch(function(e) { nameErr.textContent = firstError(e); nameErr.style.display = 'block'; });
}

// ── Edit Project ──────────────────────────────────────────────────────────────
function openEditProject(id, name, companyId, isActive) {
    document.getElementById('proj-edit-' + id).classList.add('open');
    document.getElementById('edit-proj-name-' + id).value = name;
    var sel = document.getElementById('edit-proj-company-' + id);
    if (sel) sel.value = companyId || '';
    document.getElementById('edit-proj-active-' + id).checked = isActive;
    var errEl = document.getElementById('edit-proj-error-' + id);
    if (errEl) errEl.style.display = 'none';
    document.getElementById('edit-proj-name-' + id).focus();
}
function closeEditProject(id) { document.getElementById('proj-edit-' + id).classList.remove('open'); }

function saveProject(id) {
    var name      = document.getElementById('edit-proj-name-' + id).value.trim();
    var isActive  = document.getElementById('edit-proj-active-' + id).checked;
    var sel       = document.getElementById('edit-proj-company-' + id);
    var companyId = sel ? (sel.value || null) : null;
    var errEl     = document.getElementById('edit-proj-error-' + id);
    errEl.style.display = 'none';
    if (!name) { errEl.textContent = 'Name is required.'; errEl.style.display = 'block'; return; }
    api(BASE + '/' + id, 'PATCH', { name: name, company_id: companyId, is_active: isActive ? 1 : 0 })
        .then(function(data) {
            var p = data.project;
            closeEditProject(id);
            document.getElementById('proj-name-' + id).textContent = p.name;
            var badge = document.getElementById('proj-inactive-' + id);
            if (badge) badge.style.display = p.is_active ? 'none' : '';
            var icon = document.getElementById('proj-icon-' + id);
            if (icon) icon.setAttribute('stroke', p.is_active ? '#2563eb' : '#9ca3af');
            var coEl = document.getElementById('proj-company-' + id);
            if (coEl) {
                var co = COMPANIES.find(function(c) { return c.id == p.company_id; });
                coEl.textContent = co ? co.name : '';
                coEl.style.display = co ? '' : 'none';
            }
            showToast('Project updated.', 'success');
        })
        .catch(function(e) { errEl.textContent = firstError(e); errEl.style.display = 'block'; });
}

function deleteProject(id, name) {
    confirmAction('Delete Project', 'Delete "' + name + '" and all its locations?', function() {
        api(BASE + '/' + id, 'DELETE')
            .then(function() {
                var locCount = document.getElementById('loc-list-' + id)
                    ? document.getElementById('loc-list-' + id).querySelectorAll('[id^="loc-wrap-"]').length : 0;
                var card = document.getElementById('proj-card-' + id);
                if (card) card.remove();
                var list = document.getElementById('projects-list');
                if (list && !list.querySelector('[id^="proj-card-"]')) {
                    list.innerHTML = '<div id="projects-empty" class="card card-body" style="text-align:center;padding:3rem;color:#9ca3af;">No projects yet. Click "Add Project" to create one.</div>';
                }
                updateStat('stat-projects', -1);
                updateStat('stat-locations', -locCount);
                showToast('Project "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

// ── Location CRUD ─────────────────────────────────────────────────────────────
var _locMode = 'add', _locId = null, _projId = null;

function openAddLocModal(projectId) {
    _locMode = 'add'; _locId = null; _projId = projectId;
    var projName = document.getElementById('proj-name-' + projectId);
    document.getElementById('loc-modal-title').textContent = 'Add Location' + (projName ? ' — ' + projName.textContent.trim() : '');
    document.getElementById('loc-modal-name').value    = '';
    document.getElementById('loc-modal-address').value = '';
    document.getElementById('loc-modal-lat').value     = '';
    document.getElementById('loc-modal-lng').value     = '';
    document.getElementById('loc-modal-active').checked = true;
    document.getElementById('loc-modal-name-error').style.display = 'none';
    document.getElementById('loc-modal-error').style.display = 'none';
    document.getElementById('loc-modal-overlay').classList.add('open');
    setTimeout(function() { initMap(); locMap.invalidateSize(); removeMapPin(); locMap.setView(defaultCenter, defaultZoom); }, 60);
}

function openEditLocModal(locId, projectId) {
    _locMode = 'edit'; _locId = locId; _projId = projectId;
    var d = LOC_DATA[locId] || {};
    document.getElementById('loc-modal-title').textContent = 'Edit Location';
    document.getElementById('loc-modal-name').value    = d.name    || '';
    document.getElementById('loc-modal-address').value = d.address || '';
    document.getElementById('loc-modal-lat').value     = d.latitude  != null ? d.latitude  : '';
    document.getElementById('loc-modal-lng').value     = d.longitude != null ? d.longitude : '';
    document.getElementById('loc-modal-active').checked = d.is_active !== false;
    document.getElementById('loc-modal-name-error').style.display = 'none';
    document.getElementById('loc-modal-error').style.display = 'none';
    document.getElementById('loc-modal-overlay').classList.add('open');
    setTimeout(function() {
        initMap(); locMap.invalidateSize(); removeMapPin();
        if (d.latitude != null && d.longitude != null) setMapPin(parseFloat(d.latitude), parseFloat(d.longitude));
        else locMap.setView(defaultCenter, defaultZoom);
    }, 60);
}

function closeLocModal() { document.getElementById('loc-modal-overlay').classList.remove('open'); }

function saveLocModal() {
    var name     = document.getElementById('loc-modal-name').value.trim();
    var address  = document.getElementById('loc-modal-address').value.trim() || null;
    var latRaw   = document.getElementById('loc-modal-lat').value.trim();
    var lngRaw   = document.getElementById('loc-modal-lng').value.trim();
    var isActive = document.getElementById('loc-modal-active').checked;
    var nameErr  = document.getElementById('loc-modal-name-error');
    var genErr   = document.getElementById('loc-modal-error');
    nameErr.style.display = 'none'; genErr.style.display = 'none';
    if (!name) { nameErr.textContent = 'Location name is required.'; nameErr.style.display = 'block'; return; }
    var payload = { name: name, address: address,
        latitude:  latRaw !== '' ? parseFloat(latRaw) : null,
        longitude: lngRaw !== '' ? parseFloat(lngRaw) : null,
        is_active: isActive ? 1 : 0 };
    var url    = BASE + '/' + _projId + '/locations' + (_locMode === 'edit' ? '/' + _locId : '');
    var method = _locMode === 'edit' ? 'PATCH' : 'POST';
    api(url, method, payload)
        .then(function(data) {
            var loc = data.location;
            LOC_DATA[loc.id] = loc;
            closeLocModal();
            if (_locMode === 'add') {
                var emptyEl = document.getElementById('loc-empty-' + _projId);
                if (emptyEl) emptyEl.remove();
                var list = document.getElementById('loc-list-' + _projId);
                var div  = document.createElement('div');
                div.innerHTML = buildLocRow(_projId, loc);
                list.appendChild(div.firstElementChild);
                updateLocCount(_projId, 1);
                updateStat('stat-locations', 1);
                showToast('Location "' + esc(loc.name) + '" added.', 'success');
            } else {
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
                    } else { gpsEl.style.display = 'none'; }
                }
                showToast('Location updated.', 'success');
            }
        })
        .catch(function(e) { genErr.textContent = firstError(e); genErr.style.display = 'block'; });
}

function deleteLoc(projectId, locId, name) {
    confirmAction('Delete Location', 'Delete "' + name + '"?', function() {
        api(BASE + '/' + projectId + '/locations/' + locId, 'DELETE')
            .then(function() {
                delete LOC_DATA[locId];
                var wrap = document.getElementById('loc-wrap-' + locId);
                if (wrap) wrap.remove();
                updateLocCount(projectId, -1);
                updateStat('stat-locations', -1);
                var list = document.getElementById('loc-list-' + projectId);
                if (list && !list.querySelector('[id^="loc-wrap-"]')) {
                    list.innerHTML = '<div id="loc-empty-' + projectId + '" style="padding:14px 1.25rem;color:#9ca3af;font-size:13px;">No locations yet — click &quot;+ Location&quot; to add one.</div>';
                }
                showToast('Location "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

// ── Stat & count helpers ──────────────────────────────────────────────────────
function updateStat(id, delta) {
    var el = document.getElementById(id);
    if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
}
function updateLocCount(projectId, delta) {
    var el = document.getElementById('proj-loc-count-' + projectId);
    if (!el) return;
    var n = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
    el.textContent = n + ' ' + (n === 1 ? 'location' : 'locations');
}

// ── Leaflet Map ───────────────────────────────────────────────────────────────
var locMap = null, locMarker = null;
var defaultCenter = [25.2048, 55.2708], defaultZoom = 11;

if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
        defaultCenter = [pos.coords.latitude, pos.coords.longitude];
        defaultZoom = 13;
        if (locMap && !locMarker) locMap.setView(defaultCenter, defaultZoom);
    }, null, { timeout: 6000 });
}

function initMap() {
    if (locMap) return;
    locMap = L.map('loc-map').setView(defaultCenter, defaultZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>', maxZoom: 19
    }).addTo(locMap);
    locMap.on('click', function(e) {
        setMapPin(e.latlng.lat, e.latlng.lng);
        document.getElementById('loc-modal-lat').value = e.latlng.lat.toFixed(7);
        document.getElementById('loc-modal-lng').value = e.latlng.lng.toFixed(7);
    });
}
function setMapPin(lat, lng) {
    var latlng = L.latLng(lat, lng);
    if (locMarker) { locMarker.setLatLng(latlng); }
    else {
        locMarker = L.marker(latlng, { draggable: true }).addTo(locMap);
        locMarker.on('dragend', function(e) {
            var pos = e.target.getLatLng();
            document.getElementById('loc-modal-lat').value = pos.lat.toFixed(7);
            document.getElementById('loc-modal-lng').value = pos.lng.toFixed(7);
        });
    }
    locMap.setView(latlng, Math.max(locMap.getZoom(), 14));
}
function removeMapPin() { if (locMarker) { locMarker.remove(); locMarker = null; } }
function onLatLngInput() {
    var lat = parseFloat(document.getElementById('loc-modal-lat').value);
    var lng = parseFloat(document.getElementById('loc-modal-lng').value);
    if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) setMapPin(lat, lng);
}
function geocodeAddress() {
    var addr = document.getElementById('loc-modal-address').value.trim();
    if (!addr) { showToast('Enter an address to search.', 'warn'); return; }
    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr) + '&limit=1', {
        headers: { 'Accept-Language': 'en', 'User-Agent': 'OperationModule/1.0' }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data && data.length) {
            var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
            document.getElementById('loc-modal-lat').value = lat.toFixed(7);
            document.getElementById('loc-modal-lng').value = lng.toFixed(7);
            setMapPin(lat, lng);
        } else { showToast('Address not found — try a more specific query.', 'warn'); }
    }).catch(function() { showToast('Geocoding failed. Check your connection.', 'error'); });
}

// ── JS Card Builders ──────────────────────────────────────────────────────────
function buildProjectCard(p) {
    var pName   = esc(p.name);
    var rawName = (p.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    var co      = COMPANIES.find(function(c) { return c.id == p.company_id; });
    var coHtml  = co ? '<span id="proj-company-' + p.id + '" style="font-size:12px;color:#3b82f6;opacity:.8;background:#dbeafe;padding:1px 8px;border-radius:9px;">' + esc(co.name) + '</span>'
                     : '<span id="proj-company-' + p.id + '" style="display:none;"></span>';
    var selectHtml = '<select id="edit-proj-company-' + p.id + '" class="form-input" style="font-size:13px;width:auto;min-width:140px;"><option value="">— No company —</option>';
    COMPANIES.forEach(function(c) { selectHtml += '<option value="' + c.id + '"' + (c.id == p.company_id ? ' selected' : '') + '>' + esc(c.name) + '</option>'; });
    selectHtml += '</select>';
    return '<div id="proj-card-' + p.id + '" class="proj-card">'
        + '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;background:linear-gradient(135deg,#eff6ff,#dbeafe);">'
        +   '<div style="display:flex;align-items:center;gap:10px;">'
        +     '<svg width="16" height="16" fill="none" stroke="#2563eb" viewBox="0 0 24 24" style="flex-shrink:0;" id="proj-icon-' + p.id + '"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>'
        +     '<span id="proj-name-' + p.id + '" style="font-size:15px;font-weight:700;color:#1e40af;">' + pName + '</span>'
        +     '<span id="proj-inactive-' + p.id + '" class="badge-inactive" style="display:none;">Inactive</span>'
        +     coHtml
        +     '<span id="proj-loc-count-' + p.id + '" style="font-size:12px;color:#6366f1;opacity:.75;">0 locations</span>'
        +   '</div>'
        +   '<div style="display:flex;gap:6px;align-items:center;">'
        +     '<button type="button" onclick="openAddLocModal(' + p.id + ')" class="btn-secondary btn-sm" style="border-color:#93c5fd;color:#1d4ed8;">+ Location</button>'
        +     '<button type="button" onclick="openEditProject(' + p.id + ', \'' + rawName + '\', ' + (p.company_id || 'null') + ', true)" class="btn-secondary btn-sm">Edit</button>'
        +     '<button type="button" onclick="deleteProject(' + p.id + ', \'' + rawName + '\')" class="btn-danger btn-sm">Delete</button>'
        +   '</div>'
        + '</div>'
        + '<div class="proj-edit-strip" id="proj-edit-' + p.id + '">'
        +   '<input id="edit-proj-name-' + p.id + '" type="text" class="form-input" style="flex:1;font-size:13px;" onkeydown="if(event.key===\'Enter\') saveProject(' + p.id + '); if(event.key===\'Escape\') closeEditProject(' + p.id + ')">'
        +   selectHtml
        +   '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;"><input type="checkbox" id="edit-proj-active-' + p.id + '" checked style="width:14px;height:14px;"> Active</label>'
        +   '<button type="button" onclick="saveProject(' + p.id + ')" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>'
        +   '<button type="button" onclick="closeEditProject(' + p.id + ')" class="btn-secondary btn-sm">Cancel</button>'
        +   '<p id="edit-proj-error-' + p.id + '" class="field-error" style="margin:0;"></p>'
        + '</div>'
        + '<div id="loc-list-' + p.id + '"><div id="loc-empty-' + p.id + '" style="padding:14px 1.25rem;color:#9ca3af;font-size:13px;">No locations yet — click &quot;+ Location&quot; to add one.</div></div>'
        + '</div>';
}

function buildLocRow(projectId, loc) {
    var safeName = (loc.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    var addrHtml = loc.address
        ? '<div id="loc-address-' + loc.id + '" style="font-size:12px;color:#64748b;margin-top:2px;">' + esc(loc.address) + '</div>'
        : '<div id="loc-address-' + loc.id + '" style="font-size:12px;color:#64748b;margin-top:2px;display:none;"></div>';
    var gpsHtml = (loc.latitude != null && loc.longitude != null)
        ? '<div id="loc-gps-' + loc.id + '" style="font-size:11px;color:#94a3b8;margin-top:1px;font-family:monospace;">' + parseFloat(loc.latitude).toFixed(6) + '°, ' + parseFloat(loc.longitude).toFixed(6) + '°</div>'
        : '<div id="loc-gps-' + loc.id + '" style="font-size:11px;color:#94a3b8;margin-top:1px;font-family:monospace;display:none;"></div>';
    return '<div id="loc-wrap-' + loc.id + '">'
        + '<div class="loc-row" id="loc-row-' + loc.id + '">'
        +   '<div style="display:flex;align-items:flex-start;gap:8px;flex:1;min-width:0;">'
        +     '<svg width="13" height="13" fill="none" stroke="#22c55e" viewBox="0 0 24 24" id="loc-icon-' + loc.id + '" style="flex-shrink:0;margin-top:3px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
        +     '<div style="flex:1;min-width:0;">'
        +       '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span id="loc-name-' + loc.id + '" style="font-size:13px;font-weight:600;color:#1e293b;">' + esc(loc.name) + '</span><span id="loc-inactive-' + loc.id + '" class="badge-inactive" style="display:none;">Inactive</span></div>'
        +       addrHtml + gpsHtml
        +     '</div>'
        +   '</div>'
        +   '<div style="display:flex;gap:4px;flex-shrink:0;margin-top:1px;">'
        +     '<button type="button" onclick="openEditLocModal(' + loc.id + ', ' + projectId + ')" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>'
        +     '<button type="button" onclick="deleteLoc(' + projectId + ', ' + loc.id + ', \'' + safeName + '\')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>'
        +   '</div>'
        + '</div>'
        + '</div>';
}
</script>
@endsection
