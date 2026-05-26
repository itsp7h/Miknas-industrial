@extends('layouts.app')

@section('title', 'Settings — Integrations')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Settings — Integrations</h1>
    <p class="page-subtitle">Configure third-party service integrations.</p>
</div>

<div style="max-width:680px;" x-data="{ tab: 'whatsapp' }">

    {{-- Pill tabs --}}
    <div style="display:flex;gap:8px;margin-bottom:20px;">
        <button type="button" @click="tab='whatsapp'"
            :style="tab==='whatsapp' ? 'background:#1e293b;color:#fff;border:1px solid transparent;' : 'background:#fff;color:#374151;border:1px solid #d1d5db;'"
            style="padding:7px 18px;border-radius:999px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;">
            💬 WhatsApp
        </button>
        <button type="button" @click="tab='email'"
            :style="tab==='email' ? 'background:#1e293b;color:#fff;border:1px solid transparent;' : 'background:#fff;color:#374151;border:1px solid #d1d5db;'"
            style="padding:7px 18px;border-radius:999px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;">
            ✉️ Email
        </button>
    </div>

    {{-- ===== WhatsApp tab ===== --}}
    <div x-show="tab==='whatsapp'">

        <div class="card">
            <div style="padding:20px 24px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;">
                <svg style="width:24px;height:24px;color:#22c55e;flex-shrink:0;" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                <h3 style="font-size:16px;font-weight:600;color:#111827;margin:0;">WhatsApp (UltraMSG)</h3>
            </div>

            <div style="padding:24px;">

                {{-- Enable toggle --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                    <div>
                        <p style="font-size:14px;font-weight:500;color:#374151;margin:0 0 2px;">Enable WhatsApp Notifications</p>
                        <p style="font-size:12px;color:#6b7280;margin:0;">When disabled, no messages will be sent.</p>
                    </div>
                    <div style="position:relative;display:inline-flex;align-items:center;cursor:pointer;">
                        <input type="hidden" id="wa-enabled-hidden" value="{{ $whatsappSettings['enabled'] ? '1' : '0' }}">
                        <div id="wa-toggle-track" onclick="toggleWaSwitch()" style="
                            width:44px;height:24px;border-radius:12px;cursor:pointer;
                            background:{{ $whatsappSettings['enabled'] ? '#22c55e' : '#d1d5db' }};
                            position:relative;transition:background .2s;">
                            <div id="wa-toggle-thumb" style="
                                position:absolute;top:2px;
                                left:{{ $whatsappSettings['enabled'] ? '22px' : '2px' }};
                                width:20px;height:20px;border-radius:50%;background:#fff;
                                box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .2s;"></div>
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">

                {{-- Instance ID --}}
                <div style="margin-bottom:16px;">
                    <label class="form-label">Instance ID</label>
                    <input type="text" id="wa-instance-id"
                        value="{{ $whatsappSettings['instance_id'] }}"
                        placeholder="e.g. instance177593"
                        class="form-input">
                </div>

                {{-- API Token --}}
                <div style="margin-bottom:16px;" x-data="{ showWaToken: false }">
                    <label class="form-label">API Token</label>
                    <div style="position:relative;">
                        <input :type="showWaToken ? 'text' : 'password'" id="wa-token"
                            value="{{ $whatsappSettings['token'] }}"
                            placeholder="Your UltraMSG token"
                            class="form-input" style="padding-right:40px;">
                        <button type="button" @click="showWaToken = !showWaToken"
                            style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;"
                            onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                            <svg x-show="!showWaToken" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showWaToken" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Webhook Secret --}}
                <div style="margin-bottom:16px;" x-data="{ showWaSecret: false }">
                    <label class="form-label">
                        Webhook Secret <span style="color:#9ca3af;font-weight:400;">(optional)</span>
                    </label>
                    <div style="position:relative;">
                        <input :type="showWaSecret ? 'text' : 'password'" id="wa-webhook-secret"
                            value="{{ $whatsappSettings['webhook_secret'] }}"
                            placeholder="Leave empty to skip HMAC verification"
                            class="form-input" style="padding-right:40px;">
                        <button type="button" @click="showWaSecret = !showWaSecret"
                            style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;"
                            onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                            <svg x-show="!showWaSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showWaSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Webhook Path --}}
                <div style="margin-bottom:24px;">
                    <label class="form-label">Webhook Path</label>
                    <div style="display:flex;align-items:stretch;">
                        <span style="display:inline-flex;align-items:center;padding:0 12px;font-size:13px;color:#6b7280;background:#f9fafb;border:1px solid #d1d5db;border-right:none;border-radius:6px 0 0 6px;white-space:nowrap;">{{ url('/') }}/</span>
                        <input type="text" id="wa-webhook-path"
                            value="{{ $whatsappSettings['webhook_path'] }}"
                            class="form-input" style="border-radius:0 6px 6px 0;flex:1;">
                    </div>
                    <p style="font-size:12px;color:#6b7280;margin-top:4px;">
                        Paste this full URL in your UltraMSG dashboard: <strong>{{ url('/') }}/{{ $whatsappSettings['webhook_path'] }}</strong>
                    </p>
                </div>

                {{-- Actions --}}
                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid #f3f4f6;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <button type="button" id="btn-wa-test" onclick="testWaConnection()"
                            style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;text-decoration:underline;text-underline-offset:2px;padding:0;"
                            onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'">
                            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Test Connection
                        </button>
                        <span id="wa-conn-status" style="font-size:13px;display:none;"></span>
                    </div>
                    <button type="button" id="btn-wa-save" onclick="saveWhatsapp()" class="btn-primary">Save Settings</button>
                </div>

            </div>
        </div>

        {{-- Send Test Message accordion --}}
        <div class="card" style="margin-top:16px;">
            <button type="button" onclick="toggleWaMsg()"
                style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:none;border:none;cursor:pointer;text-align:left;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <svg style="width:18px;height:18px;color:#22c55e;flex-shrink:0;" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <span style="font-size:14px;font-weight:600;color:#111827;">Send Test Message</span>
                    <span style="font-size:12px;color:#6b7280;">— verify the connection works end-to-end</span>
                </div>
                <svg id="wa-msg-chevron" style="width:16px;height:16px;color:#9ca3af;transition:transform .2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div id="wa-msg-body" style="display:none;padding:0 24px 24px;">
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Phone Number</label>
                    <input type="text" id="wa-test-to" placeholder="+97333165444" class="form-input" style="width:100%;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Message</label>
                    <textarea id="wa-test-body" rows="3" class="form-input" style="width:100%;resize:vertical;">Test message from SteelERP — WhatsApp integration is working!</textarea>
                </div>
                <div style="display:flex;align-items:center;gap:14px;">
                    <button type="button" id="btn-wa-send" onclick="sendWaTestMessage()"
                        style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;font-size:13px;font-weight:600;color:#fff;background:#22c55e;border:none;border-radius:8px;cursor:pointer;"
                        onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
                        <svg style="width:15px;height:15px;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Send Message
                    </button>
                    <span id="wa-send-status" style="font-size:13px;display:none;"></span>
                </div>
            </div>
        </div>

    </div>{{-- end WhatsApp tab --}}

    {{-- ===== Email tab ===== --}}
    <div x-show="tab==='email'" style="display:none;">

        <div class="card">
            <div style="padding:16px 24px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;">
                <div style="width:32px;height:32px;background:#eff6ff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">✉️</div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#111827;">Microsoft 365 (Azure Mail)</div>
                    <div style="font-size:12px;color:#6b7280;">Send emails via Microsoft Graph API using Azure AD</div>
                </div>
            </div>

            <div style="padding:24px;">

                {{-- Enable toggle --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                    <div>
                        <p style="font-size:14px;font-weight:500;color:#374151;margin:0 0 2px;">Enable Email Notifications</p>
                        <p style="font-size:12px;color:#6b7280;margin:0;">When disabled, no emails will be sent.</p>
                    </div>
                    <div style="position:relative;display:inline-flex;align-items:center;cursor:pointer;">
                        <input type="hidden" id="em-enabled-hidden" value="{{ $azureSettings['enabled'] ? '1' : '0' }}">
                        <div id="em-toggle-track" onclick="toggleEmSwitch()" style="
                            width:44px;height:24px;border-radius:12px;cursor:pointer;
                            background:{{ $azureSettings['enabled'] ? '#22c55e' : '#d1d5db' }};
                            position:relative;transition:background .2s;">
                            <div id="em-toggle-thumb" style="
                                position:absolute;top:2px;
                                left:{{ $azureSettings['enabled'] ? '22px' : '2px' }};
                                width:20px;height:20px;border-radius:50%;background:#fff;
                                box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .2s;"></div>
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">

                {{-- Tenant ID --}}
                <div style="margin-bottom:16px;">
                    <label class="form-label">Tenant ID</label>
                    <input type="text" id="em-tenant-id"
                        value="{{ $azureSettings['tenant_id'] }}"
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        class="form-input">
                </div>

                {{-- Client ID --}}
                <div style="margin-bottom:16px;">
                    <label class="form-label">Client ID</label>
                    <input type="text" id="em-client-id"
                        value="{{ $azureSettings['client_id'] }}"
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        class="form-input">
                </div>

                {{-- Client Secret --}}
                <div style="margin-bottom:16px;" x-data="{ showEmSecret: false }">
                    <label class="form-label">Client Secret</label>
                    <div style="position:relative;">
                        <input :type="showEmSecret ? 'text' : 'password'" id="em-client-secret"
                            value="{{ $azureSettings['client_secret'] }}"
                            placeholder="Your Azure AD client secret"
                            class="form-input" style="padding-right:40px;">
                        <button type="button" @click="showEmSecret = !showEmSecret"
                            style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;"
                            onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                            <svg x-show="!showEmSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showEmSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- From Address --}}
                <div style="margin-bottom:24px;">
                    <label class="form-label">From Address</label>
                    <input type="text" id="em-from-address"
                        value="{{ $azureSettings['from_address'] }}"
                        placeholder="noreply@yourdomain.com"
                        class="form-input">
                    <p style="font-size:12px;color:#6b7280;margin-top:4px;">Must be a mailbox in your Microsoft 365 tenant.</p>
                </div>

                {{-- Actions --}}
                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid #f3f4f6;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <button type="button" id="btn-em-test" onclick="testAzureConnection()"
                            style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;text-decoration:underline;text-underline-offset:2px;padding:0;"
                            onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'">
                            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Test Connection
                        </button>
                        <span id="em-conn-status" style="font-size:13px;display:none;"></span>
                    </div>
                    <button type="button" id="btn-em-save" onclick="saveAzureMail()" class="btn-primary">Save Settings</button>
                </div>

            </div>
        </div>

        {{-- Send Test Email accordion --}}
        <div class="card" style="margin-top:16px;">
            <button type="button" onclick="toggleEmMsg()"
                style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:none;border:none;cursor:pointer;text-align:left;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:16px;">📧</span>
                    <span style="font-size:14px;font-weight:600;color:#111827;">Send Test Email</span>
                    <span style="font-size:12px;color:#6b7280;">— verify the connection works end-to-end</span>
                </div>
                <svg id="em-msg-chevron" style="width:16px;height:16px;color:#9ca3af;transition:transform .2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div id="em-msg-body" style="display:none;padding:0 24px 24px;">
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">To</label>
                    <input type="text" id="em-test-to" placeholder="recipient@example.com" class="form-input" style="width:100%;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Subject</label>
                    <input type="text" id="em-test-subject" value="Test Email from SteelERP" class="form-input" style="width:100%;">
                </div>
                <div style="display:flex;align-items:center;gap:14px;">
                    <button type="button" id="btn-em-send" onclick="sendTestEmail()"
                        style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;font-size:13px;font-weight:600;color:#fff;background:#2563eb;border:none;border-radius:8px;cursor:pointer;"
                        onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                        ✉️ Send Email
                    </button>
                    <span id="em-send-status" style="font-size:13px;display:none;"></span>
                </div>
            </div>
        </div>

    </div>{{-- end Email tab --}}

</div>

<script>
var CSRF = document.querySelector('meta[name="csrf-token"]').content;

function api(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(body) {
            if (!r.ok) return Promise.reject(body);
            return body;
        });
    });
}

function toggleWaSwitch() {
    var hidden = document.getElementById('wa-enabled-hidden');
    var track  = document.getElementById('wa-toggle-track');
    var thumb  = document.getElementById('wa-toggle-thumb');
    var on = hidden.value === '1';
    hidden.value = on ? '0' : '1';
    track.style.background = on ? '#d1d5db' : '#22c55e';
    thumb.style.left = on ? '2px' : '22px';
}

function toggleEmSwitch() {
    var hidden = document.getElementById('em-enabled-hidden');
    var track  = document.getElementById('em-toggle-track');
    var thumb  = document.getElementById('em-toggle-thumb');
    var on = hidden.value === '1';
    hidden.value = on ? '0' : '1';
    track.style.background = on ? '#d1d5db' : '#22c55e';
    thumb.style.left = on ? '2px' : '22px';
}

var _waMsgOpen = false;
function toggleWaMsg() {
    _waMsgOpen = !_waMsgOpen;
    document.getElementById('wa-msg-body').style.display = _waMsgOpen ? 'block' : 'none';
    document.getElementById('wa-msg-chevron').style.transform = _waMsgOpen ? 'rotate(180deg)' : '';
}

var _emMsgOpen = false;
function toggleEmMsg() {
    _emMsgOpen = !_emMsgOpen;
    document.getElementById('em-msg-body').style.display = _emMsgOpen ? 'block' : 'none';
    document.getElementById('em-msg-chevron').style.transform = _emMsgOpen ? 'rotate(180deg)' : '';
}

function saveWhatsapp() {
    var btn = document.getElementById('btn-wa-save');
    btn.disabled = true; btn.style.opacity = '.6';
    api('{{ route('settings.integrations.whatsapp') }}', {
        enabled:        document.getElementById('wa-enabled-hidden').value,
        instance_id:    document.getElementById('wa-instance-id').value.trim(),
        token:          document.getElementById('wa-token').value.trim(),
        webhook_secret: document.getElementById('wa-webhook-secret').value.trim(),
        webhook_path:   document.getElementById('wa-webhook-path').value.trim(),
    }).then(function() {
        showToast('WhatsApp settings saved.', 'success');
    }).catch(function(err) {
        var msg = err.errors ? Object.values(err.errors)[0][0] : (err.message || 'Error saving settings.');
        showToast(msg, 'error');
    }).finally(function() { btn.disabled = false; btn.style.opacity = '1'; });
}

function testWaConnection() {
    var statusEl = document.getElementById('wa-conn-status');
    statusEl.textContent = 'Testing…'; statusEl.style.display = ''; statusEl.style.color = '#6b7280';
    fetch('{{ route('settings.integrations.test-whatsapp') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            statusEl.textContent = 'Connected ✓'; statusEl.style.color = '#16a34a';
        } else {
            statusEl.textContent = 'Failed: ' + (data.message || 'Unknown error'); statusEl.style.color = '#dc2626';
        }
    }).catch(function() { statusEl.textContent = 'Request failed.'; statusEl.style.color = '#dc2626'; });
}

function sendWaTestMessage() {
    var to   = document.getElementById('wa-test-to').value.trim();
    var body = document.getElementById('wa-test-body').value.trim();
    if (!to)   { showToast('Enter a phone number.', 'warn'); return; }
    if (!body) { showToast('Enter a message.', 'warn'); return; }
    var btn = document.getElementById('btn-wa-send');
    var statusEl = document.getElementById('wa-send-status');
    btn.disabled = true; btn.style.opacity = '.6';
    statusEl.textContent = 'Sending…'; statusEl.style.display = ''; statusEl.style.color = '#6b7280';
    api('{{ route('settings.integrations.send-test-message') }}', { to: to, body: body })
        .then(function() {
            statusEl.textContent = 'Sent ✓'; statusEl.style.color = '#16a34a';
            showToast('Test message sent!', 'success');
        }).catch(function(err) {
            statusEl.textContent = 'Failed.'; statusEl.style.color = '#dc2626';
            showToast(err.message || 'Failed to send.', 'error');
        }).finally(function() { btn.disabled = false; btn.style.opacity = '1'; });
}

function saveAzureMail() {
    var btn = document.getElementById('btn-em-save');
    btn.disabled = true; btn.style.opacity = '.6';
    api('{{ route('settings.integrations.azure-mail') }}', {
        enabled:       document.getElementById('em-enabled-hidden').value,
        tenant_id:     document.getElementById('em-tenant-id').value.trim(),
        client_id:     document.getElementById('em-client-id').value.trim(),
        client_secret: document.getElementById('em-client-secret').value.trim(),
        from_address:  document.getElementById('em-from-address').value.trim(),
    }).then(function() {
        showToast('Email settings saved.', 'success');
    }).catch(function(err) {
        var msg = err.errors ? Object.values(err.errors)[0][0] : (err.message || 'Error saving settings.');
        showToast(msg, 'error');
    }).finally(function() { btn.disabled = false; btn.style.opacity = '1'; });
}

function testAzureConnection() {
    var statusEl = document.getElementById('em-conn-status');
    statusEl.textContent = 'Testing…'; statusEl.style.display = ''; statusEl.style.color = '#6b7280';
    fetch('{{ route('settings.integrations.test-azure-mail') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            statusEl.textContent = 'Connected ✓'; statusEl.style.color = '#16a34a';
            showToast('Azure AD connection successful.', 'success');
        } else {
            statusEl.textContent = 'Failed: ' + (data.message || 'Unknown error'); statusEl.style.color = '#dc2626';
            showToast(data.message || 'Connection failed.', 'error');
        }
    }).catch(function() { statusEl.textContent = 'Request failed.'; statusEl.style.color = '#dc2626'; showToast('Request failed.', 'error'); });
}

function sendTestEmail() {
    var to      = document.getElementById('em-test-to').value.trim();
    var subject = document.getElementById('em-test-subject').value.trim();
    if (!to)      { showToast('Enter a recipient email.', 'warn'); return; }
    if (!subject) { showToast('Enter a subject.', 'warn'); return; }
    var btn = document.getElementById('btn-em-send');
    var statusEl = document.getElementById('em-send-status');
    btn.disabled = true; btn.style.opacity = '.6';
    statusEl.textContent = 'Sending…'; statusEl.style.display = ''; statusEl.style.color = '#6b7280';
    api('{{ route('settings.integrations.send-test-email') }}', { to: to, subject: subject })
        .then(function() {
            statusEl.textContent = 'Sent ✓'; statusEl.style.color = '#16a34a';
            showToast('Test email sent!', 'success');
        }).catch(function(err) {
            statusEl.textContent = 'Failed.'; statusEl.style.color = '#dc2626';
            showToast(err.message || 'Failed to send email.', 'error');
        }).finally(function() { btn.disabled = false; btn.style.opacity = '1'; });
}
</script>
@endsection
