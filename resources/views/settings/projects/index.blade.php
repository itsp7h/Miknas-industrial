@extends('layouts.app')

@section('title', 'Settings — Companies')

@section('content')
<style>
    .co-card { border:1px solid #e2e8f0; border-radius:0.875rem; overflow:hidden; margin-bottom:1rem; background:white; }
    .dept-row { display:flex; align-items:center; justify-content:space-between; padding:0.6rem 1rem 0.6rem 1.25rem; border-bottom:1px solid #f1f5f9; }
    .dept-edit-row { display:none; padding:0.5rem 1rem; background:#f0f9ff; border-bottom:1px solid #bae6fd; gap:6px; align-items:center; }
    .dept-edit-row.open { display:flex; }
    .co-edit-strip { display:none; padding:0.65rem 1.25rem; background:#f0f9ff; border-bottom:1px solid #bae6fd; align-items:center; gap:8px; }
    .co-edit-strip.open { display:flex; }
    .badge-inactive { display:inline-block; padding:1px 7px; border-radius:9px; font-size:11px; font-weight:600; background:#fee2e2; color:#dc2626; margin-left:6px; }
    .field-error { color:#dc2626; font-size:12px; margin-top:4px; display:none; }
    .stat-card { background:white; border:1px solid #e2e8f0; border-radius:0.75rem; padding:1.25rem 1.5rem; }
</style>

{{-- Page header --}}
<div class="mb-5" style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">Companies &amp; Departments</h1>
        <p class="page-subtitle">Manage companies and their departments.</p>
    </div>
    <button type="button" onclick="openAddCompanyModal()" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;flex-shrink:0;">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Company
    </button>
</div>

{{-- Stat boxes --}}
<div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px; margin-bottom:28px; max-width:480px;">
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
    <div class="stat-card" style="border-top:3px solid #06b6d4;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px;height:40px;background:#ecfeff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="#06b6d4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div id="stat-departments" style="font-size:28px;font-weight:700;color:#1e293b;line-height:1;">{{ $stats['total_departments'] }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">Departments</div>
            </div>
        </div>
    </div>
</div>

{{-- Add Company Modal --}}
<div id="add-company-overlay" onclick="if(event.target===this)closeAddCompanyModal()"
     style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:1rem;width:420px;max-width:96vw;box-shadow:0 25px 60px rgba(0,0,0,0.22);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #e2e8f0;">
            <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">New Company</h2>
            <button type="button" onclick="closeAddCompanyModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px;line-height:0;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div style="padding:1.25rem 1.5rem;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Company Name <span style="color:#ef4444;">*</span></label>
            <input id="new-company-input" type="text" class="form-input" style="width:100%;" placeholder="e.g. Miknas Industrial"
                onkeydown="if(event.key==='Enter') addCompany(); if(event.key==='Escape') closeAddCompanyModal()">
            <p id="new-company-error" class="field-error"></p>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:0.875rem 1.5rem;border-top:1px solid #e2e8f0;background:#f8fafc;">
            <button type="button" onclick="closeAddCompanyModal()" class="btn-secondary">Cancel</button>
            <button type="button" onclick="addCompany()" class="btn-primary">Save Company</button>
        </div>
    </div>
</div>

{{-- Companies list --}}
<div id="companies-list">
@forelse($companies as $company)

<div id="company-card-{{ $company->id }}" class="co-card">

    {{-- Company header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;background:linear-gradient(135deg,#eef2ff,#e0e7ff);">
        <div style="display:flex;align-items:center;gap:10px;">
            <svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <span id="company-name-{{ $company->id }}" style="font-size:15px;font-weight:700;color:#3730a3;">{{ $company->name }}</span>
            <span id="company-inactive-{{ $company->id }}" class="badge-inactive" style="{{ $company->is_active ? 'display:none' : '' }}">Inactive</span>
            <span id="company-dept-count-{{ $company->id }}" style="font-size:12px;color:#7c3aed;opacity:.8;">{{ $company->departments->count() }} {{ Str::plural('dept', $company->departments->count()) }}</span>
        </div>
        <div style="display:flex;gap:6px;align-items:center;">
            <button type="button" onclick="openAddDept({{ $company->id }})"
                class="btn-secondary btn-sm" style="border-color:#a78bfa;color:#6d28d9;">+ Department</button>
            <button type="button" onclick="openEditCompany({{ $company->id }}, '{{ addslashes($company->name) }}', {{ $company->is_active ? 'true' : 'false' }})"
                class="btn-secondary btn-sm">Edit</button>
            <button type="button" onclick="deleteCompany({{ $company->id }}, '{{ addslashes($company->name) }}')"
                class="btn-danger btn-sm">Delete</button>
        </div>
    </div>

    {{-- Company edit strip --}}
    <div class="co-edit-strip" id="company-edit-{{ $company->id }}">
        <input id="edit-company-name-{{ $company->id }}" type="text" class="form-input" style="flex:1;font-size:13px;">
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;">
            <input type="checkbox" id="edit-company-active-{{ $company->id }}" {{ $company->is_active ? 'checked' : '' }} style="width:14px;height:14px;">
            Active
        </label>
        <button type="button" onclick="saveCompany({{ $company->id }})" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>
        <button type="button" onclick="closeEditCompany({{ $company->id }})" class="btn-secondary btn-sm">Cancel</button>
        <p id="edit-company-error-{{ $company->id }}" class="field-error" style="margin:0;"></p>
    </div>

    {{-- Departments section --}}
    <div>
        {{-- Add dept inline row --}}
        <div class="dept-edit-row" id="dept-add-row-{{ $company->id }}">
            <input id="dept-add-name-{{ $company->id }}" type="text" class="form-input" style="flex:1;font-size:12px;" placeholder="Department name…"
                onkeydown="if(event.key==='Enter') saveDeptAdd({{ $company->id }}); if(event.key==='Escape') closeAddDept({{ $company->id }})">
            <button type="button" onclick="saveDeptAdd({{ $company->id }})" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>
            <button type="button" onclick="closeAddDept({{ $company->id }})" class="btn-secondary btn-sm">✕</button>
        </div>

        <div id="dept-list-{{ $company->id }}">
            @forelse($company->departments as $dept)
            <div id="dept-wrap-{{ $dept->id }}">
                <div class="dept-row" id="dept-row-{{ $dept->id }}">
                    <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                        <svg width="13" height="13" fill="none" stroke="{{ $dept->is_active ? '#7c3aed' : '#9ca3af' }}" viewBox="0 0 24 24" id="dept-icon-{{ $dept->id }}" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span id="dept-name-{{ $dept->id }}" style="font-size:13px;font-weight:500;color:#1e293b;">{{ $dept->name }}</span>
                        <span id="dept-inactive-{{ $dept->id }}" class="badge-inactive" style="{{ $dept->is_active ? 'display:none' : '' }}">Inactive</span>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <button type="button" onclick="openEditDept({{ $dept->id }}, {{ $company->id }}, '{{ addslashes($dept->name) }}', {{ $dept->is_active ? 'true' : 'false' }})" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>
                        <button type="button" onclick="deleteDept({{ $company->id }}, {{ $dept->id }}, '{{ addslashes($dept->name) }}')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>
                    </div>
                </div>
                <div class="dept-edit-row" id="dept-edit-row-{{ $dept->id }}">
                    <input id="dept-edit-name-{{ $dept->id }}" type="text" class="form-input" style="flex:1;font-size:12px;"
                        onkeydown="if(event.key==='Enter') saveEditDept({{ $dept->id }}, {{ $company->id }}); if(event.key==='Escape') closeEditDept({{ $dept->id }})">
                    <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;">
                        <input type="checkbox" id="dept-edit-active-{{ $dept->id }}" {{ $dept->is_active ? 'checked' : '' }} style="width:13px;height:13px;">
                        Active
                    </label>
                    <button type="button" onclick="saveEditDept({{ $dept->id }}, {{ $company->id }})" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>
                    <button type="button" onclick="closeEditDept({{ $dept->id }})" class="btn-secondary btn-sm">✕</button>
                </div>
            </div>
            @empty
            <div id="dept-empty-{{ $company->id }}" style="padding:14px 1.25rem;color:#9ca3af;font-size:13px;">No departments yet — click "+ Department" to add one.</div>
            @endforelse
        </div>
    </div>

</div>{{-- end company-card --}}

@empty
<div id="companies-empty" class="card card-body" style="text-align:center;padding:3rem;color:#9ca3af;">
    No companies yet. Add your first company above.
</div>
@endforelse
</div>{{-- end companies-list --}}

<script>
var CSRF     = document.querySelector('meta[name="csrf-token"]').content;
var BASE_CO  = '{{ url("settings/projects/companies") }}';
var BASE_PRJ = '{{ url("settings/projects") }}';

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

// ── Company CRUD ──────────────────────────────────────────────────────────────
function openAddCompanyModal() {
    document.getElementById('new-company-input').value = '';
    document.getElementById('new-company-error').style.display = 'none';
    document.getElementById('add-company-overlay').style.display = 'flex';
    setTimeout(function() { document.getElementById('new-company-input').focus(); }, 50);
}
function closeAddCompanyModal() {
    document.getElementById('add-company-overlay').style.display = 'none';
}

function addCompany() {
    var input = document.getElementById('new-company-input');
    var err   = document.getElementById('new-company-error');
    var name  = input.value.trim();
    err.style.display = 'none';
    if (!name) { err.textContent = 'Company name is required.'; err.style.display = 'block'; return; }
    api(BASE_CO, 'POST', { name: name })
        .then(function(data) {
            closeAddCompanyModal();
            var c = data.company;
            var emptyEl = document.getElementById('companies-empty');
            if (emptyEl) emptyEl.remove();
            var list = document.getElementById('companies-list');
            var div  = document.createElement('div');
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
    document.getElementById('edit-company-name-' + id).focus();
}
function closeEditCompany(id) { document.getElementById('company-edit-' + id).classList.remove('open'); }

function saveCompany(id) {
    var name     = document.getElementById('edit-company-name-' + id).value.trim();
    var isActive = document.getElementById('edit-company-active-' + id).checked;
    var errEl    = document.getElementById('edit-company-error-' + id);
    errEl.style.display = 'none';
    if (!name) { errEl.textContent = 'Name is required.'; errEl.style.display = 'block'; return; }
    api(BASE_CO + '/' + id, 'PATCH', { name: name, is_active: isActive ? 1 : 0 })
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
    confirmAction('Delete Company', 'Delete "' + name + '" and all its departments?', function() {
        api(BASE_CO + '/' + id, 'DELETE')
            .then(function() {
                var deptCount = document.getElementById('dept-list-' + id)
                    ? document.getElementById('dept-list-' + id).querySelectorAll('[id^="dept-wrap-"]').length : 0;
                var card = document.getElementById('company-card-' + id);
                if (card) card.remove();
                var list = document.getElementById('companies-list');
                if (list && !list.querySelector('[id^="company-card-"]')) {
                    list.innerHTML = '<div id="companies-empty" class="card card-body" style="text-align:center;padding:3rem;color:#9ca3af;">No companies yet. Add your first company above.</div>';
                }
                updateStat('stat-companies', -1);
                updateStat('stat-departments', -deptCount);
                showToast('Company "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

// ── Department CRUD ───────────────────────────────────────────────────────────
function openAddDept(companyId) {
    var row = document.getElementById('dept-add-row-' + companyId);
    row.classList.add('open');
    var input = document.getElementById('dept-add-name-' + companyId);
    input.value = '';
    input.focus();
}
function closeAddDept(companyId) {
    document.getElementById('dept-add-row-' + companyId).classList.remove('open');
}
function saveDeptAdd(companyId) {
    var input = document.getElementById('dept-add-name-' + companyId);
    var name  = input.value.trim();
    if (!name) { showToast('Department name is required.', 'warn'); return; }
    api(BASE_CO + '/' + companyId + '/departments', 'POST', { name: name })
        .then(function(data) {
            var dept = data.department;
            closeAddDept(companyId);
            input.value = '';
            var emptyEl = document.getElementById('dept-empty-' + companyId);
            if (emptyEl) emptyEl.remove();
            var list = document.getElementById('dept-list-' + companyId);
            var div  = document.createElement('div');
            div.innerHTML = buildDeptRow(companyId, dept);
            insertDeptInOrder(companyId, div.firstElementChild);
            updateDeptCount(companyId);
            updateStat('stat-departments', 1);
            showToast('Department "' + esc(dept.name) + '" added.', 'success');
        })
        .catch(function(e) { showToast(firstError(e), 'error'); });
}

function openEditDept(deptId, companyId, name, isActive) {
    document.getElementById('dept-row-' + deptId).style.display = 'none';
    var row = document.getElementById('dept-edit-row-' + deptId);
    row.classList.add('open');
    document.getElementById('dept-edit-name-' + deptId).value    = name;
    document.getElementById('dept-edit-active-' + deptId).checked = isActive;
    document.getElementById('dept-edit-name-' + deptId).focus();
}
function closeEditDept(deptId) {
    document.getElementById('dept-edit-row-' + deptId).classList.remove('open');
    document.getElementById('dept-row-' + deptId).style.display = '';
}
function saveEditDept(deptId, companyId) {
    var name     = document.getElementById('dept-edit-name-' + deptId).value.trim();
    var isActive = document.getElementById('dept-edit-active-' + deptId).checked;
    if (!name) { showToast('Department name is required.', 'warn'); return; }
    api(BASE_CO + '/' + companyId + '/departments/' + deptId, 'PATCH', { name: name, is_active: isActive ? 1 : 0 })
        .then(function(data) {
            var dept = data.department;
            closeEditDept(deptId);
            document.getElementById('dept-name-' + deptId).textContent = dept.name;
            var badge = document.getElementById('dept-inactive-' + deptId);
            if (badge) badge.style.display = dept.is_active ? 'none' : '';
            var icon = document.getElementById('dept-icon-' + deptId);
            if (icon) icon.setAttribute('stroke', dept.is_active ? '#7c3aed' : '#9ca3af');
            showToast('Department updated.', 'success');
        })
        .catch(function(e) { showToast(firstError(e), 'error'); });
}
function deleteDept(companyId, deptId, name) {
    confirmAction('Delete Department', 'Delete "' + name + '"?', function() {
        api(BASE_CO + '/' + companyId + '/departments/' + deptId, 'DELETE')
            .then(function() {
                var wrap = document.getElementById('dept-wrap-' + deptId);
                if (wrap) wrap.remove();
                updateDeptCount(companyId);
                updateStat('stat-departments', -1);
                var list = document.getElementById('dept-list-' + companyId);
                if (list && !list.querySelector('[id^="dept-wrap-"]')) {
                    list.innerHTML = '<div id="dept-empty-' + companyId + '" style="padding:14px 1.25rem;color:#9ca3af;font-size:13px;">No departments yet — click &quot;+ Department&quot; to add one.</div>';
                }
                showToast('Department "' + esc(name) + '" deleted.', 'success');
            })
            .catch(function(e) { showToast(firstError(e), 'error'); });
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function updateStat(id, delta) {
    var el = document.getElementById(id);
    if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
}

function updateDeptCount(companyId) {
    var list  = document.getElementById('dept-list-' + companyId);
    var count = list ? list.querySelectorAll('[id^="dept-wrap-"]').length : 0;
    var el    = document.getElementById('company-dept-count-' + companyId);
    if (el) el.textContent = count + ' ' + (count === 1 ? 'dept' : 'depts');
}

function insertDeptInOrder(companyId, newWrapEl) {
    var list    = document.getElementById('dept-list-' + companyId);
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

function buildCompanyCard(c) {
    var cName   = esc(c.name);
    var rawName = (c.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    return '<div id="company-card-' + c.id + '" class="co-card">'
        + '<div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.25rem;background:linear-gradient(135deg,#eef2ff,#e0e7ff);">'
        +   '<div style="display:flex;align-items:center;gap:10px;">'
        +     '<svg width="18" height="18" fill="none" stroke="#6366f1" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>'
        +     '<span id="company-name-' + c.id + '" style="font-size:15px;font-weight:700;color:#3730a3;">' + cName + '</span>'
        +     '<span id="company-inactive-' + c.id + '" class="badge-inactive" style="display:none;">Inactive</span>'
        +     '<span id="company-dept-count-' + c.id + '" style="font-size:12px;color:#7c3aed;opacity:.8;">0 depts</span>'
        +   '</div>'
        +   '<div style="display:flex;gap:6px;align-items:center;">'
        +     '<button type="button" onclick="openAddDept(' + c.id + ')" class="btn-secondary btn-sm" style="border-color:#a78bfa;color:#6d28d9;">+ Department</button>'
        +     '<button type="button" onclick="openEditCompany(' + c.id + ', \'' + rawName + '\', true)" class="btn-secondary btn-sm">Edit</button>'
        +     '<button type="button" onclick="deleteCompany(' + c.id + ', \'' + rawName + '\')" class="btn-danger btn-sm">Delete</button>'
        +   '</div>'
        + '</div>'
        + '<div class="co-edit-strip" id="company-edit-' + c.id + '">'
        +   '<input id="edit-company-name-' + c.id + '" type="text" class="form-input" style="flex:1;font-size:13px;">'
        +   '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;"><input type="checkbox" id="edit-company-active-' + c.id + '" checked style="width:14px;height:14px;"> Active</label>'
        +   '<button type="button" onclick="saveCompany(' + c.id + ')" class="btn-primary" style="padding:5px 14px;font-size:12px;white-space:nowrap;">Save</button>'
        +   '<button type="button" onclick="closeEditCompany(' + c.id + ')" class="btn-secondary btn-sm">Cancel</button>'
        +   '<p id="edit-company-error-' + c.id + '" class="field-error" style="margin:0;"></p>'
        + '</div>'
        + '<div>'
        +   '<div class="dept-edit-row" id="dept-add-row-' + c.id + '">'
        +     '<input id="dept-add-name-' + c.id + '" type="text" class="form-input" style="flex:1;font-size:12px;" placeholder="Department name…" onkeydown="if(event.key===\'Enter\') saveDeptAdd(' + c.id + '); if(event.key===\'Escape\') closeAddDept(' + c.id + ')">'
        +     '<button type="button" onclick="saveDeptAdd(' + c.id + ')" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>'
        +     '<button type="button" onclick="closeAddDept(' + c.id + ')" class="btn-secondary btn-sm">✕</button>'
        +   '</div>'
        +   '<div id="dept-list-' + c.id + '"><div id="dept-empty-' + c.id + '" style="padding:14px 1.25rem;color:#9ca3af;font-size:13px;">No departments yet — click &quot;+ Department&quot; to add one.</div></div>'
        + '</div>'
        + '</div>';
}

function buildDeptRow(companyId, dept) {
    var safeName = (dept.name || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
    return '<div id="dept-wrap-' + dept.id + '">'
        + '<div class="dept-row" id="dept-row-' + dept.id + '">'
        +   '<div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">'
        +     '<svg width="13" height="13" fill="none" stroke="#7c3aed" viewBox="0 0 24 24" id="dept-icon-' + dept.id + '" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
        +     '<span id="dept-name-' + dept.id + '" style="font-size:13px;font-weight:500;color:#1e293b;">' + esc(dept.name) + '</span>'
        +     '<span id="dept-inactive-' + dept.id + '" class="badge-inactive" style="display:none;">Inactive</span>'
        +   '</div>'
        +   '<div style="display:flex;gap:4px;flex-shrink:0;">'
        +     '<button type="button" onclick="openEditDept(' + dept.id + ',' + companyId + ',\'' + safeName + '\',true)" class="btn-secondary btn-sm" style="padding:3px 8px;font-size:12px;">Edit</button>'
        +     '<button type="button" onclick="deleteDept(' + companyId + ',' + dept.id + ',\'' + safeName + '\')" class="btn-danger btn-sm" style="padding:3px 8px;font-size:12px;">Delete</button>'
        +   '</div>'
        + '</div>'
        + '<div class="dept-edit-row" id="dept-edit-row-' + dept.id + '">'
        +   '<input id="dept-edit-name-' + dept.id + '" type="text" class="form-input" style="flex:1;font-size:12px;" onkeydown="if(event.key===\'Enter\') saveEditDept(' + dept.id + ',' + companyId + '); if(event.key===\'Escape\') closeEditDept(' + dept.id + ')">'
        +   '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;white-space:nowrap;cursor:pointer;"><input type="checkbox" id="dept-edit-active-' + dept.id + '" checked style="width:13px;height:13px;"> Active</label>'
        +   '<button type="button" onclick="saveEditDept(' + dept.id + ',' + companyId + ')" class="btn-primary" style="padding:4px 12px;font-size:12px;white-space:nowrap;">Save</button>'
        +   '<button type="button" onclick="closeEditDept(' + dept.id + ')" class="btn-secondary btn-sm">✕</button>'
        + '</div>'
        + '</div>';
}
</script>
@endsection
