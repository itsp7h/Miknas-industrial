@extends('layouts.app')

@section('title', 'Settings — VAT')

@section('content')
<div class="mb-5">
    <h1 class="page-title">VAT Settings</h1>
    <p class="page-subtitle">Set the global VAT rate applied to vatable items on supplier quotes.</p>
</div>

<div style="max-width:480px;">
    <div style="background:white;border:1px solid #e2e8f0;border-radius:0.875rem;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">VAT Configuration</div>
        </div>
        <div style="padding:1.5rem;">
            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">
                VAT Rate (%)
            </label>
            <div style="display:flex;align-items:center;gap:10px;">
                <input type="number" id="vat-rate-input" value="{{ $vatRate }}"
                       min="0" max="100" step="0.01" placeholder="e.g. 10"
                       style="width:160px;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:600;outline:none;"
                       onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
                <span style="font-size:14px;color:#64748b;font-weight:500;">%</span>
            </div>
            <p style="font-size:12px;color:#94a3b8;margin-top:8px;">
                Enter 0 to disable VAT. Suppliers will see a VAT checkbox on each item when this is greater than 0.
            </p>
            <button onclick="saveVat()"
                    style="margin-top:20px;padding:10px 24px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                Save VAT Rate
            </button>
        </div>
    </div>
</div>

<script>
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
function saveVat() {
    var rate = document.getElementById('vat-rate-input').value;
    fetch('{{ route('settings.vat.update') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ vat_rate: rate })
    }).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    }).then(function() {
        showToast('VAT rate saved.', 'success');
    }).catch(function(err) {
        var msg = (err.errors && err.errors.vat_rate) ? err.errors.vat_rate[0] : (err.message || 'Failed to save.');
        showToast(msg, 'error');
    });
}
</script>
@endsection
