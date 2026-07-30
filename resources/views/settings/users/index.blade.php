@extends('layouts.app')

@section('title', 'Settings — Users')

@section('content')
<div class="mb-5">
    <h1 class="page-title">User Management</h1>
    <p class="page-subtitle">Assign profiles and toggle individual permissions for each employee.</p>
</div>

<div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
    <input type="text" id="user-search" placeholder="Search users…"
           style="width:100%;max-width:320px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;"
           onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
    <span id="search-count" style="font-size:12px;color:#94a3b8;font-weight:500;">{{ $users->count() }}</span>
</div>

<div style="background:white;border:1px solid #e2e8f0;border-radius:0.875rem;overflow:hidden;">
    <table class="table-base" style="width:100%;">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Profiles</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr class="data-row" data-user-id="{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td class="text-gray-500">{{ $user->email }}</td>
                <td>
                    @forelse($user->roles as $role)
                        <span style="display:inline-block;background:#eff6ff;color:#2563eb;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;margin-right:4px;">{{ $role->name }}</span>
                    @empty
                        <span style="color:#cbd5e1;font-size:12px;">No profile</span>
                    @endforelse
                </td>
                <td class="text-right">
                    <button class="btn-secondary btn-sm"
                            data-user-id="{{ $user->id }}"
                            data-user-name="{{ $user->name }}"
                            data-roles="{{ $user->roles->pluck('name')->toJson() }}"
                            data-permissions="{{ $user->permissions->pluck('name')->toJson() }}"
                            onclick="openAccessModal(this)">
                        Edit Access
                    </button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p id="no-results" class="hidden-by-search" style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">No users match your search.</p>
</div>

{{-- ── Edit access modal ── --}}
<div id="access-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;width:100%;max-width:520px;max-height:85vh;overflow-y:auto;">
        <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h2 id="access-modal-title" style="font-size:15px;font-weight:700;color:#0f172a;">Edit Access</h2>
            <button onclick="closeAccessModal()" style="background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:20px 24px;">
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Profiles</div>
            <div id="access-roles-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;">
                @foreach($roles as $role)
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;">
                    <input type="checkbox" class="access-role-checkbox" value="{{ $role }}">
                    {{ $role }}
                </label>
                @endforeach
            </div>

            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Individual Permissions</div>
            <div id="access-permissions-list" style="display:flex;flex-direction:column;gap:10px;">
                @foreach($permissions as $permission)
                <label style="display:flex;align-items:center;justify-content:space-between;font-size:13px;color:#374151;">
                    <span>{{ $permission['label'] }}</span>
                    <span class="toggle-switch" style="position:relative;display:inline-block;width:38px;height:20px;">
                        <input type="checkbox" class="access-permission-checkbox" value="{{ $permission['name'] }}" style="opacity:0;width:0;height:0;">
                        <span class="toggle-slider" style="position:absolute;inset:0;background:#e2e8f0;border-radius:999px;transition:.15s;cursor:pointer;"></span>
                    </span>
                </label>
                @endforeach
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
            <button class="btn-secondary" onclick="closeAccessModal()">Cancel</button>
            <button class="btn-primary" onclick="saveAccess()">Save</button>
        </div>
    </div>
</div>

<style>
    .hidden-by-search { display: none; }
    .access-permission-checkbox:checked + .toggle-slider { background: #2563eb; }
    .access-permission-checkbox:checked + .toggle-slider::before { transform: translateX(18px); }
    .toggle-slider::before {
        content: ''; position: absolute; height: 16px; width: 16px; left: 2px; top: 2px;
        background: white; border-radius: 50%; transition: .15s;
    }
</style>

<script>
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
var currentUserId = null;

function openAccessModal(btn) {
    var userId      = btn.dataset.userId;
    var userName    = btn.dataset.userName;
    var roles       = JSON.parse(btn.dataset.roles);
    var permissions = JSON.parse(btn.dataset.permissions);

    currentUserId = userId;
    document.getElementById('access-modal-title').textContent = 'Edit Access — ' + userName;

    document.querySelectorAll('.access-role-checkbox').forEach(function(cb) {
        cb.checked = roles.indexOf(cb.value) !== -1;
    });
    document.querySelectorAll('.access-permission-checkbox').forEach(function(cb) {
        cb.checked = permissions.indexOf(cb.value) !== -1;
    });

    document.getElementById('access-modal').style.display = 'flex';
}

function closeAccessModal() {
    document.getElementById('access-modal').style.display = 'none';
    currentUserId = null;
}

function saveAccess() {
    var roles = Array.prototype.slice.call(document.querySelectorAll('.access-role-checkbox:checked')).map(function(cb) { return cb.value; });
    var permissions = Array.prototype.slice.call(document.querySelectorAll('.access-permission-checkbox:checked')).map(function(cb) { return cb.value; });

    fetch('/settings/users/' + currentUserId, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ roles: roles, permissions: permissions })
    }).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    }).then(function(body) {
        closeAccessModal();
        showToast(body.message, 'success');
        setTimeout(function() { window.location.reload(); }, 600);
    }).catch(function(err) {
        showToast(err.message || 'Failed to update access.', 'error');
    });
}

// ── Instant client-side search ──
var searchInput = document.getElementById('user-search');
var rows        = document.querySelectorAll('.data-row');
var countEl     = document.getElementById('search-count');
var noResults   = document.getElementById('no-results');
var total       = rows.length;

searchInput.addEventListener('input', function() {
    var q = this.value.trim().toLowerCase();
    var visible = 0;
    rows.forEach(function(row) {
        var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
        row.classList.toggle('hidden-by-search', !match);
        if (match) visible++;
    });
    countEl.textContent = q ? (visible + ' of ' + total) : total;
    noResults.classList.toggle('hidden-by-search', visible !== 0);
});
</script>
@endsection
