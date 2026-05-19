@extends('layouts.app')

@section('title', 'Settings — Integrations')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Settings — Integrations</h1>
    <p class="page-subtitle">Configure third-party service integrations.</p>
</div>

<div style="max-width:680px;">

    <div class="card">
        <div style="padding:20px 24px 16px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:12px;">
            <svg style="width:24px;height:24px;color:#22c55e;flex-shrink:0;" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <h3 style="font-size:16px;font-weight:600;color:#111827;margin:0;">WhatsApp (UltraMSG)</h3>
        </div>

        <form method="POST" action="{{ route('settings.integrations.whatsapp') }}" style="padding:24px;">
            @csrf

            {{-- Enable toggle --}}
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <div>
                    <p style="font-size:14px;font-weight:500;color:#374151;margin:0 0 2px;">Enable WhatsApp Notifications</p>
                    <p style="font-size:12px;color:#6b7280;margin:0;">When disabled, no messages will be sent.</p>
                </div>
                <label style="position:relative;display:inline-flex;align-items:center;cursor:pointer;">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1"
                        {{ $settings['enabled'] ? 'checked' : '' }}
                        id="toggle-enabled"
                        style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">
                    <div id="toggle-track" onclick="toggleSwitch()" style="
                        width:44px;height:24px;border-radius:12px;cursor:pointer;
                        background:{{ $settings['enabled'] ? '#22c55e' : '#d1d5db' }};
                        position:relative;transition:background .2s;
                    ">
                        <div id="toggle-thumb" style="
                            position:absolute;top:2px;
                            left:{{ $settings['enabled'] ? '22px' : '2px' }};
                            width:20px;height:20px;border-radius:50%;background:#fff;
                            box-shadow:0 1px 3px rgba(0,0,0,.2);
                            transition:left .2s;
                        "></div>
                    </div>
                </label>
            </div>

            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">

            {{-- Instance ID --}}
            <div style="margin-bottom:16px;">
                <label class="form-label">Instance ID</label>
                <input type="text" name="instance_id" value="{{ old('instance_id', $settings['instance_id']) }}"
                    placeholder="e.g. instance123456"
                    class="form-input @error('instance_id') border-red-400 @enderror">
                @error('instance_id') <p style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            {{-- API Token --}}
            <div style="margin-bottom:16px;" x-data="{ showToken: false }">
                <label class="form-label">API Token</label>
                <div style="position:relative;">
                    <input :type="showToken ? 'text' : 'password'" name="token"
                        value="{{ old('token', $settings['token']) }}"
                        placeholder="Your UltraMSG token"
                        class="form-input @error('token') border-red-400 @enderror"
                        style="padding-right:40px;">
                    <button type="button" @click="showToken = !showToken"
                        style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;"
                        onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                        <svg x-show="!showToken" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showToken" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('token') <p style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            {{-- Webhook Secret --}}
            <div style="margin-bottom:16px;" x-data="{ showSecret: false }">
                <label class="form-label">
                    Webhook Secret <span style="color:#9ca3af;font-weight:400;">(optional)</span>
                </label>
                <div style="position:relative;">
                    <input :type="showSecret ? 'text' : 'password'" name="webhook_secret"
                        value="{{ old('webhook_secret', $settings['webhook_secret']) }}"
                        placeholder="Leave empty to skip HMAC verification"
                        class="form-input"
                        style="padding-right:40px;">
                    <button type="button" @click="showSecret = !showSecret"
                        style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;"
                        onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                        <svg x-show="!showSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Webhook Path --}}
            <div style="margin-bottom:24px;">
                <label class="form-label">Webhook Path</label>
                <div style="display:flex;align-items:stretch;">
                    <span style="
                        display:inline-flex;align-items:center;padding:0 12px;
                        font-size:13px;color:#6b7280;background:#f9fafb;
                        border:1px solid #d1d5db;border-right:none;border-radius:6px 0 0 6px;
                        white-space:nowrap;
                    ">{{ url('/') }}/</span>
                    <input type="text" name="webhook_path"
                        value="{{ old('webhook_path', $settings['webhook_path']) }}"
                        class="form-input @error('webhook_path') border-red-400 @enderror"
                        style="border-radius:0 6px 6px 0;flex:1;">
                </div>
                <p style="font-size:12px;color:#6b7280;margin-top:4px;">
                    Paste this full URL in your UltraMSG dashboard: <strong>{{ url('/') }}/{{ $settings['webhook_path'] }}</strong>
                </p>
                @error('webhook_path') <p style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            {{-- Actions --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid #f3f4f6;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button type="button" id="btn-test-connection"
                        style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;text-decoration:underline;text-underline-offset:2px;padding:0;"
                        onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'">
                        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Test Connection
                    </button>
                    <span id="connection-status" style="font-size:13px;display:none;"></span>
                </div>
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>

        </form>
    </div>

</div>

<script>
// Toggle switch (pure JS, no Alpine for the hidden input sync)
function toggleSwitch() {
    var checkbox = document.querySelector('input[name="enabled"][type="checkbox"]');
    var track    = document.getElementById('toggle-track');
    var thumb    = document.getElementById('toggle-thumb');
    checkbox.checked = !checkbox.checked;
    if (checkbox.checked) {
        track.style.background = '#22c55e';
        thumb.style.left = '22px';
    } else {
        track.style.background = '#d1d5db';
        thumb.style.left = '2px';
    }
}

document.getElementById('btn-test-connection').addEventListener('click', function () {
    var statusEl = document.getElementById('connection-status');
    statusEl.textContent = 'Testing…';
    statusEl.style.display = '';
    statusEl.style.color = '#6b7280';
    statusEl.style.fontWeight = '400';

    fetch('{{ route('settings.integrations.test-whatsapp') }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            statusEl.textContent = 'Connected ✓';
            statusEl.style.color = '#16a34a';
            statusEl.style.fontWeight = '600';
        } else {
            statusEl.textContent = 'Failed: ' + data.message;
            statusEl.style.color = '#dc2626';
            statusEl.style.fontWeight = '500';
        }
    })
    .catch(function () {
        statusEl.textContent = 'Request failed.';
        statusEl.style.color = '#dc2626';
        statusEl.style.fontWeight = '500';
    });
});
</script>
@endsection
