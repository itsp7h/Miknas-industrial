@extends('layouts.app')

@section('title', 'Settings — Integrations')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Settings — Integrations</h1>
    <p class="page-subtitle">Configure third-party service integrations.</p>
</div>

<div style="max-width:900px;" x-data="{ tab: 'whatsapp' }">

    {{-- Pill tabs --}}
    <div style="display:flex;gap:8px;margin-bottom:20px;">
        <button type="button" @click="tab='whatsapp'"
            :style="tab==='whatsapp' ? 'background:#1e293b;color:#fff;border:1px solid transparent;' : 'background:#fff;color:#374151;border:1px solid #d1d5db;'"
            style="padding:7px 18px;border-radius:999px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;">
            💬 WhatsApp
        </button>
        <button type="button" @click="tab='email'; loadMailAccounts()"
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

            {{-- Two-column body --}}
            <div style="display:flex;gap:0;align-items:stretch;">

                {{-- Left: Settings form --}}
                <div style="flex:1;padding:24px;border-right:1px solid #e5e7eb;">

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

                {{-- Right: Send Test Message --}}
                <div style="width:300px;flex-shrink:0;padding:24px;background:#f9fafb;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                        <svg style="width:16px;height:16px;color:#22c55e;flex-shrink:0;" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        <span style="font-size:13px;font-weight:600;color:#111827;">Send Test Message</span>
                    </div>
                    <div style="font-size:12px;color:#6b7280;margin-bottom:16px;">Verify the connection works end-to-end by sending a real message.</div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Phone Number</label>
                        <input type="text" id="wa-test-to" placeholder="+97333165444" class="form-input">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Message</label>
                        <textarea id="wa-test-body" rows="4" class="form-input" style="resize:vertical;">Test message from SteelERP — WhatsApp integration is working!</textarea>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <button type="button" id="btn-wa-send" onclick="sendWaTestMessage()"
                            style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;font-size:13px;font-weight:600;color:#fff;background:#22c55e;border:none;border-radius:8px;cursor:pointer;"
                            onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">
                            <svg style="width:14px;height:14px;" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            Send
                        </button>
                        <span id="wa-send-status" style="font-size:12px;display:none;"></span>
                    </div>
                </div>

            </div>
        </div>

    </div>{{-- end WhatsApp tab --}}

    {{-- ===== Email tab ===== --}}
    <div x-show="tab==='email'" style="display:none;" id="email-tab-panel">

        {{-- Two-column layout: account list left, send test right --}}
        <div style="display:flex;gap:16px;align-items:flex-start;">

            {{-- Left: account list --}}
            <div style="flex:1;min-width:0;">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <div>
                        <div style="font-size:15px;font-weight:600;color:#111827;">Mail Accounts</div>
                        <div id="ma-count" style="font-size:12px;color:#6b7280;">Loading…</div>
                    </div>
                    <button type="button" onclick="maOpen()" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                        <span style="font-size:16px;line-height:1;">+</span> Add Account
                    </button>
                </div>

                <div id="ma-list" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;display:none;"></div>

                <div id="ma-empty" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:40px 24px;text-align:center;">
                    <div style="font-size:32px;margin-bottom:12px;">✉️</div>
                    <div style="font-size:14px;font-weight:600;color:#374151;margin-bottom:4px;">No mail accounts configured</div>
                    <div style="font-size:13px;color:#6b7280;">Click <strong>Add Account</strong> to get started.</div>
                </div>

            </div>

            {{-- Right: Send Test Email --}}
            <div style="width:280px;flex-shrink:0;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:8px;">
                    <span style="font-size:15px;">📧</span>
                    <span style="font-size:13px;font-weight:600;color:#111827;">Send Test Email</span>
                </div>
                <div style="padding:16px 20px;">
                    <div style="font-size:12px;color:#6b7280;margin-bottom:14px;">Send a test email via any configured account.</div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Account</label>
                        <select id="em-test-account" class="form-input">
                            <option value="">— select account —</option>
                        </select>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">To</label>
                        <input type="text" id="em-test-to" placeholder="recipient@example.com" class="form-input">
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <button type="button" id="btn-em-send" onclick="sendTestEmail()"
                            style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;font-size:13px;font-weight:600;color:#fff;background:#2563eb;border:none;border-radius:8px;cursor:pointer;"
                            onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                            ✉️ Send
                        </button>
                        <span id="em-send-status" style="font-size:12px;display:none;"></span>
                    </div>
                </div>
            </div>

        </div>

    </div>{{-- end Email tab --}}

    {{-- Mail Accounts Modal --}}
    <div id="ma-modal-overlay"
        onclick="if(event.target===this) maClose()"
        style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,0.55);backdrop-filter:blur(3px);">
        <div style="width:100%;max-width:540px;max-height:88vh;overflow-y:auto;background:#fff;border-radius:16px;box-shadow:0 25px 60px -10px rgba(0,0,0,0.3);">

            {{-- Header --}}
            <div style="padding:20px 24px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                <h3 id="ma-modal-title" style="font-size:16px;font-weight:700;color:#111827;margin:0;">Add Mail Account</h3>
                <button type="button" onclick="maClose()" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:20px;line-height:1;padding:0 4px;">&times;</button>
            </div>

            {{-- Body --}}
            <div style="padding:24px;">

                {{-- Account Name --}}
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">
                        Account Name <span style="color:#9ca3af;font-weight:400;">(used in code)</span>
                    </label>
                    <input type="text" id="ma-form-name" placeholder="e.g. customer-support" class="form-input">
                    <div style="font-size:11px;color:#9ca3af;margin-top:3px;">Lowercase letters, numbers and hyphens only.</div>
                </div>

                {{-- Label --}}
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Label</label>
                    <input type="text" id="ma-form-label" placeholder="e.g. Customer Support" class="form-input">
                </div>

                {{-- Type --}}
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Type</label>
                    <select id="ma-form-type" class="form-input" onchange="maTypeChange()">
                        <option value="smtp">📧 SMTP</option>
                        <option value="azure">✉️ Microsoft 365 (Azure AD)</option>
                    </select>
                </div>

                {{-- Azure section --}}
                <div id="ma-azure-section" style="display:none;border-top:1px solid #f3f4f6;padding-top:16px;margin-bottom:16px;">
                    <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">Azure AD Credentials</div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Tenant ID</label>
                        <input type="text" id="ma-form-azure-tenant" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="form-input">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Client ID</label>
                        <input type="text" id="ma-form-azure-client" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="form-input">
                    </div>
                    <div style="margin-bottom:0;" x-data="{ showAzureSecret: false }">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Client Secret</label>
                        <div style="position:relative;">
                            <input :type="showAzureSecret ? 'text' : 'password'" id="ma-form-azure-secret" placeholder="Your Azure AD client secret" class="form-input" style="padding-right:40px;">
                            <button type="button" @click="showAzureSecret=!showAzureSecret" style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;" onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                                <svg x-show="!showAzureSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showAzureSecret" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- SMTP section --}}
                <div id="ma-smtp-section" style="border-top:1px solid #f3f4f6;padding-top:16px;margin-bottom:16px;">
                    <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">SMTP Server</div>
                    <div style="display:flex;gap:10px;margin-bottom:12px;">
                        <div style="flex:1;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Host</label>
                            <input type="text" id="ma-form-smtp-host" placeholder="smtp.gmail.com" class="form-input">
                        </div>
                        <div style="width:80px;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Port</label>
                            <input type="number" id="ma-form-smtp-port" value="587" class="form-input">
                        </div>
                        <div style="width:100px;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Encryption</label>
                            <select id="ma-form-smtp-enc" class="form-input">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Username</label>
                        <input type="text" id="ma-form-smtp-username" placeholder="user@example.com" class="form-input">
                    </div>
                    <div style="margin-bottom:0;" x-data="{ showSmtpPass: false }">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">Password</label>
                        <div style="position:relative;">
                            <input :type="showSmtpPass ? 'text' : 'password'" id="ma-form-smtp-password" placeholder="SMTP password or app password" class="form-input" style="padding-right:40px;">
                            <button type="button" @click="showSmtpPass=!showSmtpPass" style="position:absolute;inset-y:0;right:0;padding:0 10px;background:none;border:none;cursor:pointer;color:#9ca3af;" onmouseover="this.style.color='#4b5563'" onmouseout="this.style.color='#9ca3af'">
                                <svg x-show="!showSmtpPass" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showSmtpPass" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Sender --}}
                <div style="border-top:1px solid #f3f4f6;padding-top:16px;margin-bottom:16px;">
                    <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">Sender</div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">From Address</label>
                        <input type="text" id="ma-form-from-address" placeholder="noreply@yourdomain.com" class="form-input">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;">From Name <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                        <input type="text" id="ma-form-from-name" placeholder="SteelERP" class="form-input">
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div style="padding:16px 24px;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <button type="button" id="ma-test-btn" onclick="maTest()"
                        style="font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0;"
                        onmouseover="this.style.color='#1d4ed8'" onmouseout="this.style.color='#2563eb'">
                        🔗 Test Connection
                    </button>
                    <span id="ma-test-status" style="font-size:12px;display:none;"></span>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="maClose()"
                        style="padding:8px 16px;background:#f9fafb;color:#374151;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                        Cancel
                    </button>
                    <button type="button" id="ma-save-btn" onclick="maSave()" class="btn-primary">
                        Save Account
                    </button>
                </div>
            </div>

        </div>
    </div>

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

// ===== Mail Accounts =====
var MA_BASE = '{{ url("settings/integrations/mail-accounts") }}';
var _maEditId = null;
var _maAccounts = [];

function loadMailAccounts() {
    fetch(MA_BASE, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            _maAccounts = data.accounts;
            renderMailAccounts();
            refreshEmailAccountSelect();
        });
}

function refreshEmailAccountSelect() {
    var sel = document.getElementById('em-test-account');
    if (!sel) return;
    var cur = sel.value;
    sel.innerHTML = '<option value="">— select account —</option>' +
        _maAccounts.map(function(a) {
            return '<option value="' + a.id + '"' + (String(a.id) === cur ? ' selected' : '') + '>' +
                escHtml(a.label) + ' (' + escHtml(a.name) + ')' +
            '</option>';
        }).join('');
}

function renderMailAccounts() {
    var list  = document.getElementById('ma-list');
    var empty = document.getElementById('ma-empty');
    var count = document.getElementById('ma-count');
    count.textContent = _maAccounts.length + ' account' + (_maAccounts.length !== 1 ? 's' : '') + ' configured';
    if (_maAccounts.length === 0) {
        list.style.display  = 'none';
        empty.style.display = '';
        return;
    }
    list.style.display  = '';
    empty.style.display = 'none';
    list.innerHTML = _maAccounts.map(renderAccountRow).join('');
}

function renderAccountRow(a) {
    var isAzure   = a.type === 'azure';
    var typeLabel = isAzure ? 'Microsoft 365' : 'SMTP';
    var typeBg    = isAzure ? '#eff6ff' : '#f0fdf4';
    var typeColor = isAzure ? '#2563eb' : '#16a34a';
    var typeIcon  = isAzure ? '✉️' : '📧';
    var trackBg   = a.enabled ? '#22c55e' : '#d1d5db';
    var thumbLeft = a.enabled ? '22px' : '2px';
    return '<div style="padding:16px 20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid #f3f4f6;" data-id="' + a.id + '">' +
        '<div style="width:36px;height:36px;background:' + typeBg + ';border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:17px;">' + typeIcon + '</div>' +
        '<div style="flex:1;min-width:0;">' +
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px;">' +
                '<span style="font-size:14px;font-weight:600;color:#111827;">' + escHtml(a.label) + '</span>' +
                '<code style="font-size:11px;color:#6b7280;background:#f3f4f6;padding:1px 6px;border-radius:4px;">' + escHtml(a.name) + '</code>' +
                '<span style="font-size:11px;font-weight:600;color:' + typeColor + ';background:' + typeBg + ';padding:2px 8px;border-radius:999px;">' + typeLabel + '</span>' +
            '</div>' +
            '<div style="font-size:12px;color:#6b7280;">' + escHtml(a.from_address) + '</div>' +
        '</div>' +
        '<div onclick="maToggle(' + a.id + ')" id="ma-toggle-' + a.id + '" style="width:44px;height:24px;border-radius:12px;background:' + trackBg + ';position:relative;cursor:pointer;flex-shrink:0;transition:background .2s;">' +
            '<div id="ma-thumb-' + a.id + '" style="position:absolute;top:2px;left:' + thumbLeft + ';width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .2s;"></div>' +
        '</div>' +
        '<div style="display:flex;gap:8px;flex-shrink:0;">' +
            '<button onclick="maOpen(' + a.id + ')" style="font-size:12px;color:#6b7280;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:5px 10px;cursor:pointer;">Edit</button>' +
            '<button onclick="maDelete(' + a.id + ',\'' + escJs(a.label) + '\')" style="font-size:12px;color:#dc2626;background:#fff5f5;border:1px solid #fecaca;border-radius:6px;padding:5px 10px;cursor:pointer;">Delete</button>' +
        '</div>' +
    '</div>';
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(s)   { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

function maTypeChange() {
    var type = document.getElementById('ma-form-type').value;
    document.getElementById('ma-azure-section').style.display = type === 'azure' ? 'block' : 'none';
    document.getElementById('ma-smtp-section').style.display  = type === 'smtp'  ? 'block' : 'none';
}

function maOpen(id) {
    _maEditId = id || null;
    document.getElementById('ma-modal-title').textContent = id ? 'Edit Mail Account' : 'Add Mail Account';
    document.getElementById('ma-form-name').value         = '';
    document.getElementById('ma-form-name').disabled      = !!id;
    document.getElementById('ma-form-label').value        = '';
    document.getElementById('ma-form-type').value         = 'smtp';
    document.getElementById('ma-form-from-address').value = '';
    document.getElementById('ma-form-from-name').value    = '';
    ['ma-form-azure-tenant','ma-form-azure-client','ma-form-azure-secret',
     'ma-form-smtp-host','ma-form-smtp-username','ma-form-smtp-password'].forEach(function(fid) {
        document.getElementById(fid).value = '';
    });
    document.getElementById('ma-form-smtp-port').value = '587';
    document.getElementById('ma-form-smtp-enc').value  = 'tls';
    document.getElementById('ma-test-status').style.display = 'none';
    maTypeChange();

    if (id) {
        fetch(MA_BASE + '/' + id, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var a = data.account, cfg = a.config || {};
                document.getElementById('ma-form-name').value         = a.name;
                document.getElementById('ma-form-label').value        = a.label;
                document.getElementById('ma-form-type').value         = a.type;
                document.getElementById('ma-form-from-address').value = a.from_address;
                document.getElementById('ma-form-from-name').value    = a.from_name || '';
                if (a.type === 'azure') {
                    document.getElementById('ma-form-azure-tenant').value = cfg.tenant_id     || '';
                    document.getElementById('ma-form-azure-client').value = cfg.client_id     || '';
                    document.getElementById('ma-form-azure-secret').value = cfg.client_secret || '';
                } else {
                    document.getElementById('ma-form-smtp-host').value     = cfg.host       || '';
                    document.getElementById('ma-form-smtp-port').value     = cfg.port       || 587;
                    document.getElementById('ma-form-smtp-enc').value      = cfg.encryption || 'tls';
                    document.getElementById('ma-form-smtp-username').value = cfg.username   || '';
                    document.getElementById('ma-form-smtp-password').value = cfg.password   || '';
                }
                maTypeChange();
            });
    }
    document.getElementById('ma-modal-overlay').style.display = 'flex';
}

function maClose() {
    document.getElementById('ma-modal-overlay').style.display = 'none';
}

function maSave() {
    var type = document.getElementById('ma-form-type').value;
    var config = type === 'azure' ? {
        tenant_id:     document.getElementById('ma-form-azure-tenant').value.trim(),
        client_id:     document.getElementById('ma-form-azure-client').value.trim(),
        client_secret: document.getElementById('ma-form-azure-secret').value.trim(),
    } : {
        host:       document.getElementById('ma-form-smtp-host').value.trim(),
        port:       parseInt(document.getElementById('ma-form-smtp-port').value) || 587,
        encryption: document.getElementById('ma-form-smtp-enc').value,
        username:   document.getElementById('ma-form-smtp-username').value.trim(),
        password:   document.getElementById('ma-form-smtp-password').value,
    };

    var data = {
        name:         document.getElementById('ma-form-name').value.trim(),
        label:        document.getElementById('ma-form-label').value.trim(),
        type:         type,
        from_address: document.getElementById('ma-form-from-address').value.trim(),
        from_name:    document.getElementById('ma-form-from-name').value.trim() || null,
        config:       config,
    };

    var btn    = document.getElementById('ma-save-btn');
    var url    = _maEditId ? MA_BASE + '/' + _maEditId : MA_BASE;
    var method = _maEditId ? 'PUT' : 'POST';
    btn.disabled = true; btn.style.opacity = '.6';

    fetch(url, {
        method: method,
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(function(r) {
        return r.json().then(function(body) { if (!r.ok) return Promise.reject(body); return body; });
    }).then(function() {
        maClose();
        showToast(_maEditId ? 'Account updated.' : 'Account added.', 'success');
        loadMailAccounts();
    }).catch(function(err) {
        var msg = err.errors ? Object.values(err.errors)[0][0] : (err.message || 'Error saving account.');
        showToast(msg, 'error');
    }).finally(function() { btn.disabled = false; btn.style.opacity = '1'; });
}

function maDelete(id, label) {
    confirmAction('Delete Mail Account', 'Delete "' + label + '"? This cannot be undone.', function() {
        fetch(MA_BASE + '/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (res.success) { showToast('Account deleted.', 'success'); loadMailAccounts(); }
        });
    });
}

function maToggle(id) {
    fetch(MA_BASE + '/' + id + '/toggle', {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (!res.success) return;
        var track = document.getElementById('ma-toggle-' + id);
        var thumb = document.getElementById('ma-thumb-' + id);
        if (track && thumb) {
            track.style.background = res.enabled ? '#22c55e' : '#d1d5db';
            thumb.style.left       = res.enabled ? '22px'   : '2px';
        }
        var acc = _maAccounts.find(function(a) { return a.id === id; });
        if (acc) acc.enabled = res.enabled;
    });
}

function maTest() {
    if (!_maEditId) { showToast('Save the account first, then test it.', 'warn'); return; }
    var statusEl = document.getElementById('ma-test-status');
    statusEl.textContent = 'Testing…'; statusEl.style.display = ''; statusEl.style.color = '#6b7280';
    fetch(MA_BASE + '/' + _maEditId + '/test', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            statusEl.textContent = 'Connected ✓'; statusEl.style.color = '#16a34a';
            showToast('Connection successful.', 'success');
        } else {
            statusEl.textContent = 'Failed.'; statusEl.style.color = '#dc2626';
            showToast(data.message || 'Connection failed.', 'error');
        }
    }).catch(function() { statusEl.textContent = 'Request failed.'; statusEl.style.color = '#dc2626'; });
}

function sendTestEmail() {
    var accountId = document.getElementById('em-test-account').value;
    var to        = document.getElementById('em-test-to').value.trim();
    if (!accountId) { showToast('Select a mail account.', 'warn'); return; }
    if (!to)        { showToast('Enter a recipient email.', 'warn'); return; }
    var btn      = document.getElementById('btn-em-send');
    var statusEl = document.getElementById('em-send-status');
    btn.disabled = true; btn.style.opacity = '.6';
    statusEl.textContent = 'Sending…'; statusEl.style.display = ''; statusEl.style.color = '#6b7280';
    fetch(MA_BASE + '/' + accountId + '/send-test', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ to: to })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            statusEl.textContent = 'Sent ✓'; statusEl.style.color = '#16a34a';
            showToast('Test email sent!', 'success');
        } else {
            statusEl.textContent = 'Failed.'; statusEl.style.color = '#dc2626';
            showToast(data.message || 'Failed to send.', 'error');
        }
    }).catch(function() {
        statusEl.textContent = 'Request failed.'; statusEl.style.color = '#dc2626';
    }).finally(function() { btn.disabled = false; btn.style.opacity = '1'; });
}
</script>
@endsection
