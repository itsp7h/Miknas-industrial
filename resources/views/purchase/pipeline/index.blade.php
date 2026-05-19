@extends('layouts.app')

@section('title', 'Purchase Pipeline')

@section('content')
<style>
  .hidden-tab { display:none; }
  .pr-row:hover { background:#f8fafc; cursor:pointer; }
  .stage-pill { display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
  <div>
    <h1 style="font-size:22px;font-weight:700;color:#0f172a;">Purchase Pipeline</h1>
    <p style="font-size:13px;color:#64748b;margin-top:3px;">Track every purchase from request to payment</p>
  </div>
  <a href="{{ route('purchase.requests.create') }}"
     style="padding:10px 22px;background:#2563eb;color:#fff;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;">
    + New Request
  </a>
</div>

{{-- Tabs --}}
<div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:20px;">
  <button id="tab-active" onclick="switchTab('active')"
    style="padding:10px 22px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:#2563eb;border-bottom:2px solid #2563eb;margin-bottom:-2px;">
    Active
    <span style="margin-left:6px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">{{ $active->count() }}</span>
  </button>
  <button id="tab-completed" onclick="switchTab('completed')"
    style="padding:10px 22px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;">
    Completed
    <span style="margin-left:6px;background:#f1f5f9;color:#64748b;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">{{ $completed->count() }}</span>
  </button>
</div>

{{-- Active tab --}}
<div id="pane-active">
  @if($active->isEmpty())
    <div style="text-align:center;padding:60px 0;color:#94a3b8;">
      <div style="font-size:44px;margin-bottom:12px;">✅</div>
      <div style="font-size:15px;font-weight:600;">No active pipelines</div>
      <div style="font-size:13px;margin-top:4px;">All purchases are complete, or create a new request.</div>
    </div>
  @else
    @include('purchase.pipeline._table', ['rows' => $active, 'stages' => $stages])
  @endif
</div>

{{-- Completed tab --}}
<div id="pane-completed" class="hidden-tab">
  @if($completed->isEmpty())
    <div style="text-align:center;padding:60px 0;color:#94a3b8;">
      <div style="font-size:44px;margin-bottom:12px;">📋</div>
      <div style="font-size:15px;font-weight:600;">No completed purchases yet</div>
    </div>
  @else
    @include('purchase.pipeline._table', ['rows' => $completed, 'stages' => $stages])
  @endif
</div>

<script>
function switchTab(tab) {
  document.getElementById('pane-active').classList.toggle('hidden-tab', tab !== 'active');
  document.getElementById('pane-completed').classList.toggle('hidden-tab', tab !== 'completed');

  var btnActive    = document.getElementById('tab-active');
  var btnCompleted = document.getElementById('tab-completed');

  if (tab === 'active') {
    btnActive.style.color = '#2563eb'; btnActive.style.borderBottomColor = '#2563eb';
    btnCompleted.style.color = '#64748b'; btnCompleted.style.borderBottomColor = 'transparent';
  } else {
    btnCompleted.style.color = '#2563eb'; btnCompleted.style.borderBottomColor = '#2563eb';
    btnActive.style.color = '#64748b'; btnActive.style.borderBottomColor = 'transparent';
  }
}
</script>
@endsection
