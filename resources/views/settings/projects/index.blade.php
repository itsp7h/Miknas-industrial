@extends('layouts.app')

@section('title', 'Settings — Projects')

@section('content')
<style>
    .proj-row { cursor:pointer; transition:background .15s; }
    .proj-row:hover { background:#f8fafc; }
    .proj-row.selected { background:#eff6ff; }
    .loc-row { transition:background .15s; }
    .loc-row:hover { background:#f8fafc; }
    .edit-form { display:none; }
    .edit-form.open { display:flex; }
    .badge-active   { display:inline-block; padding:2px 8px; border-radius:9px; font-size:11px; font-weight:600; background:#dcfce7; color:#16a34a; }
    .badge-inactive { display:inline-block; padding:2px 8px; border-radius:9px; font-size:11px; font-weight:600; background:#fee2e2; color:#dc2626; }
</style>

<div class="mb-6">
    <h1 class="page-title">Settings — Projects</h1>
    <p class="page-subtitle">Manage projects and their sub-locations.</p>
</div>

{{-- Two-panel layout --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start;">

    {{-- ── LEFT PANEL: Projects ── --}}
    <div class="card">
        <div style="padding:16px 20px 14px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
            <h3 style="font-size:15px; font-weight:600; color:#111827; margin:0;">
                Projects
                <span style="font-size:12px; font-weight:400; color:#6b7280; margin-left:6px;">{{ $projects->count() }}</span>
            </h3>
        </div>

        {{-- Add Project form --}}
        <div style="padding:16px 20px; border-bottom:1px solid #f3f4f6;">
            <form method="POST" action="{{ route('settings.projects.store') }}" style="display:flex; gap:8px; align-items:flex-start;">
                @csrf
                <div style="flex:1;">
                    <input type="text" name="name" value="{{ old('name') }}"
                        placeholder="New project name…"
                        class="form-input @error('name') border-red-400 @enderror"
                        style="width:100%;">
                    @error('name')
                        <p style="color:#dc2626; font-size:12px; margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-primary" style="white-space:nowrap; flex-shrink:0;">Add Project</button>
            </form>
        </div>

        {{-- Projects list --}}
        <div style="padding:8px 0;">
            @forelse($projects as $project)
            <div class="proj-row" id="proj-row-{{ $project->id }}" data-id="{{ $project->id }}" onclick="selectProject({{ $project->id }})">

                {{-- Display row --}}
                <div class="proj-display-{{ $project->id }}" style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px;">
                    <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
                        <svg width="14" height="14" fill="none" stroke="{{ $project->is_active ? '#22c55e' : '#9ca3af' }}" viewBox="0 0 24 24" style="flex-shrink:0;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                        <span style="font-size:13.5px; color:#1e293b; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            {{ $project->name }}
                        </span>
                        @if(!$project->is_active)
                            <span class="badge-inactive">Inactive</span>
                        @endif
                        <span style="font-size:11px; color:#94a3b8; flex-shrink:0;">{{ $project->locations->count() }} loc.</span>
                    </div>
                    <div style="display:flex; gap:4px; flex-shrink:0;" onclick="event.stopPropagation()">
                        <button type="button"
                            onclick="openEditProject({{ $project->id }}, '{{ addslashes($project->name) }}', {{ $project->is_active ? 'true' : 'false' }})"
                            class="btn-secondary btn-sm" style="padding:3px 8px; font-size:12px;">Edit</button>
                        <form method="POST" action="{{ route('settings.projects.destroy', $project) }}" id="del-proj-{{ $project->id }}">
                            @csrf @method('DELETE')
                            <button type="button"
                                onclick="confirmDelete(document.getElementById('del-proj-{{ $project->id }}'), 'Delete Project', 'Delete &quot;{{ addslashes($project->name) }}&quot; and all its locations?')"
                                class="btn-danger btn-sm" style="padding:3px 8px; font-size:12px;">Delete</button>
                        </form>
                    </div>
                </div>

                {{-- Edit inline form --}}
                <div class="edit-form proj-edit-{{ $project->id }}" style="padding:10px 16px; background:#f8fafc; border-top:1px solid #e5e7eb; gap:8px; align-items:flex-start;" onclick="event.stopPropagation()">
                    <form method="POST" action="{{ route('settings.projects.update', $project) }}" style="display:flex; gap:8px; align-items:center; flex:1;">
                        @csrf @method('PATCH')
                        <input type="text" name="name" id="edit-proj-name-{{ $project->id }}"
                            class="form-input" style="flex:1; font-size:13px;">
                        <label style="display:flex; align-items:center; gap:4px; font-size:12px; color:#374151; white-space:nowrap; cursor:pointer;">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" id="edit-proj-active-{{ $project->id }}"
                                style="width:14px; height:14px; cursor:pointer;">
                            Active
                        </label>
                        <button type="submit" class="btn-primary" style="padding:5px 12px; font-size:12px; white-space:nowrap;">Save</button>
                        <button type="button" onclick="closeEditProject({{ $project->id }})"
                            class="btn-secondary btn-sm" style="padding:5px 10px; font-size:12px;">Cancel</button>
                    </form>
                </div>

            </div>
            <div style="height:1px; background:#f3f4f6; margin:0 16px;"></div>
            @empty
            <div style="padding:32px 20px; text-align:center; color:#9ca3af; font-size:13.5px;">
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 8px; display:block; opacity:.4;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                No projects yet. Add one above.
            </div>
            @endforelse
        </div>
    </div>

    {{-- ── RIGHT PANEL: Locations ── --}}
    <div class="card" id="locations-panel">
        {{-- Placeholder when nothing selected --}}
        <div id="loc-placeholder" style="padding:48px 24px; text-align:center; color:#9ca3af;">
            <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 10px; display:block; opacity:.35;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p style="font-size:13.5px; margin:0;">Select a project to see its locations</p>
        </div>

        {{-- Content shown after a project is selected --}}
        <div id="loc-content" style="display:none;">
            <div style="padding:16px 20px 14px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
                <h3 style="font-size:15px; font-weight:600; color:#111827; margin:0;">
                    Locations — <span id="loc-project-name" style="color:#2563eb;"></span>
                    <span id="loc-count" style="font-size:12px; font-weight:400; color:#6b7280; margin-left:6px;"></span>
                </h3>
            </div>

            {{-- Add Location form --}}
            <div style="padding:16px 20px; border-bottom:1px solid #f3f4f6;">
                <form id="add-loc-form" method="POST" action="" style="display:flex; gap:8px; align-items:flex-start;">
                    @csrf
                    <div style="flex:1;">
                        <input type="text" name="name" id="add-loc-name"
                            placeholder="New location name…"
                            class="form-input" style="width:100%;">
                    </div>
                    <button type="submit" class="btn-primary" style="white-space:nowrap; flex-shrink:0;">Add Location</button>
                </form>
            </div>

            {{-- Locations list --}}
            <div id="loc-list" style="padding:8px 0;"></div>
        </div>
    </div>

</div>

{{-- Pre-load all project+location data as JSON for client-side rendering --}}
<script>
var projectsData = @json($projects->map(function($p) {
    return [
        'id'        => $p->id,
        'name'      => $p->name,
        'is_active' => $p->is_active,
        'locations' => $p->locations->map(fn($l) => [
            'id'        => $l->id,
            'name'      => $l->name,
            'is_active' => $l->is_active,
        ])->values(),
    ];
})->values());

// Route templates (server-rendered, safe)
var routeStoreLocation   = '{{ url("settings/projects") }}/__ID__/locations';
var routeUpdateLocation  = '{{ url("settings/projects") }}/__ID__/locations/__LOC__';
var routeDeleteLocation  = '{{ url("settings/projects") }}/__ID__/locations/__LOC__';
var csrfToken            = document.querySelector('meta[name="csrf-token"]').content;

var selectedProjectId = null;

function selectProject(id) {
    // Highlight selected row
    document.querySelectorAll('.proj-row').forEach(function(r) {
        r.classList.remove('selected');
    });
    var row = document.getElementById('proj-row-' + id);
    if (row) row.classList.add('selected');

    selectedProjectId = id;
    var proj = projectsData.find(function(p) { return p.id === id; });
    if (!proj) return;

    // Show content panel
    document.getElementById('loc-placeholder').style.display = 'none';
    document.getElementById('loc-content').style.display = '';

    // Update header
    document.getElementById('loc-project-name').textContent = proj.name;
    document.getElementById('loc-count').textContent = proj.locations.length;

    // Set add-form action
    document.getElementById('add-loc-form').action = routeStoreLocation.replace('__ID__', id);

    // Render locations
    renderLocations(proj);
}

function renderLocations(proj) {
    var list = document.getElementById('loc-list');
    var locs = proj.locations;

    if (locs.length === 0) {
        list.innerHTML = '<div style="padding:28px 20px; text-align:center; color:#9ca3af; font-size:13.5px;">No locations yet. Add one above.</div>';
        return;
    }

    var html = '';
    locs.forEach(function(loc) {
        var updateUrl = routeUpdateLocation.replace('__ID__', proj.id).replace('__LOC__', loc.id);
        var deleteUrl = routeDeleteLocation.replace('__ID__', proj.id).replace('__LOC__', loc.id);
        var activeChecked = loc.is_active ? 'checked' : '';
        var badgeHtml = loc.is_active ? '' : '<span class="badge-inactive" style="margin-left:6px;">Inactive</span>';

        html += '<div class="loc-row" id="loc-row-' + loc.id + '">';
        // Display row
        html += '<div class="loc-display-' + loc.id + '" style="display:flex; align-items:center; justify-content:space-between; padding:9px 16px;">';
        html +=   '<div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">';
        html +=     '<svg width="13" height="13" fill="none" stroke="' + (loc.is_active ? '#22c55e' : '#9ca3af') + '" viewBox="0 0 24 24" style="flex-shrink:0;">';
        html +=       '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>';
        html +=       '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>';
        html +=     '</svg>';
        html +=     '<span style="font-size:13px; color:#374151;">' + escHtml(loc.name) + '</span>';
        html +=     badgeHtml;
        html +=   '</div>';
        html +=   '<div style="display:flex; gap:4px; flex-shrink:0;">';
        html +=     '<button type="button" onclick="openEditLoc(' + loc.id + ', \'' + escJs(loc.name) + '\', ' + (loc.is_active ? 'true' : 'false') + ')" class="btn-secondary btn-sm" style="padding:3px 8px; font-size:12px;">Edit</button>';
        html +=     '<form method="POST" action="' + deleteUrl + '" id="del-loc-' + loc.id + '">';
        html +=       '<input type="hidden" name="_token" value="' + csrfToken + '">';
        html +=       '<input type="hidden" name="_method" value="DELETE">';
        html +=       '<button type="button" onclick="confirmDelete(document.getElementById(\'del-loc-' + loc.id + '\'), \'Delete Location\', \'Delete &quot;' + escJs(loc.name) + '&quot;?\')" class="btn-danger btn-sm" style="padding:3px 8px; font-size:12px;">Delete</button>';
        html +=     '</form>';
        html +=   '</div>';
        html += '</div>';
        // Edit form row
        html += '<div class="edit-form loc-edit-' + loc.id + '" style="padding:10px 16px; background:#f8fafc; border-top:1px solid #e5e7eb; gap:8px; align-items:center;">';
        html +=   '<form method="POST" action="' + updateUrl + '" style="display:flex; gap:8px; align-items:center; flex:1;">';
        html +=     '<input type="hidden" name="_token" value="' + csrfToken + '">';
        html +=     '<input type="hidden" name="_method" value="PATCH">';
        html +=     '<input type="text" name="name" id="edit-loc-name-' + loc.id + '" value="' + escHtml(loc.name) + '" class="form-input" style="flex:1; font-size:13px;">';
        html +=     '<label style="display:flex; align-items:center; gap:4px; font-size:12px; color:#374151; white-space:nowrap; cursor:pointer;">';
        html +=       '<input type="hidden" name="is_active" value="0">';
        html +=       '<input type="checkbox" name="is_active" value="1" id="edit-loc-active-' + loc.id + '" ' + activeChecked + ' style="width:14px; height:14px; cursor:pointer;">';
        html +=       ' Active';
        html +=     '</label>';
        html +=     '<button type="submit" class="btn-primary" style="padding:5px 12px; font-size:12px; white-space:nowrap;">Save</button>';
        html +=     '<button type="button" onclick="closeEditLoc(' + loc.id + ')" class="btn-secondary btn-sm" style="padding:5px 10px; font-size:12px;">Cancel</button>';
        html +=   '</form>';
        html += '</div>';
        html += '</div>';
        html += '<div style="height:1px; background:#f3f4f6; margin:0 16px;"></div>';
    });

    list.innerHTML = html;
    document.getElementById('loc-count').textContent = locs.length;
}

// ── Project edit helpers ──
function openEditProject(id, name, isActive) {
    // Hide display row, show edit form
    document.querySelector('.proj-display-' + id).style.display = 'none';
    var editEl = document.querySelector('.proj-edit-' + id);
    editEl.classList.add('open');
    document.getElementById('edit-proj-name-' + id).value = name;
    document.getElementById('edit-proj-active-' + id).checked = isActive;
}
function closeEditProject(id) {
    document.querySelector('.proj-display-' + id).style.display = '';
    document.querySelector('.proj-edit-' + id).classList.remove('open');
}

// ── Location edit helpers ──
function openEditLoc(id, name, isActive) {
    document.querySelector('.loc-display-' + id).style.display = 'none';
    var editEl = document.querySelector('.loc-edit-' + id);
    editEl.classList.add('open');
    document.getElementById('edit-loc-name-' + id).value = name;
    document.getElementById('edit-loc-active-' + id).checked = isActive;
}
function closeEditLoc(id) {
    document.querySelector('.loc-display-' + id).style.display = '';
    document.querySelector('.loc-edit-' + id).classList.remove('open');
}

// ── Utilities ──
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) {
    return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}

// Auto-select project after redirect (restore selection from URL hash or first project)
(function() {
    var hash = window.location.hash;
    var autoId = null;
    if (hash && hash.startsWith('#project-')) {
        autoId = parseInt(hash.replace('#project-', ''), 10);
    } else if (projectsData.length > 0) {
        autoId = projectsData[0].id;
    }
    if (autoId) {
        // Wait for DOM
        setTimeout(function() { selectProject(autoId); }, 0);
    }
})();

// When project form submitted, attach hash to redirect
document.querySelectorAll('.proj-row').forEach(function(row) {
    var id = parseInt(row.getAttribute('data-id'), 10);
    // Update add-location form to set hash on submit for restoring selection
});
</script>
@endsection
