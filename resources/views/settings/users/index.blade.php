@extends('layouts.app')

@section('title', 'Settings — Users')

@section('content')
<div class="mb-5">
    <h1 class="page-title">User Management</h1>
    <p class="page-subtitle">Assign profiles and permissions to users.</p>
</div>

{{-- Placeholder — full UI implemented in Task 3 of the purchase-access-control plan. --}}
<div style="background:white;border:1px solid #e2e8f0;border-radius:0.875rem;padding:1.5rem;">
    <p style="color:#64748b;font-size:13px;">{{ $users->count() }} users, {{ $roles->count() }} roles, {{ $permissions->count() }} permissions available.</p>
</div>
@endsection
