import { useEffect, useState } from 'react';
import SecretField from './SecretField';
import Switch from './Switch';
import { useToast } from '../../ui/Toast';

const SECTION_LABEL = {
    fontSize: 12, fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase',
    letterSpacing: '.06em', marginBottom: 20,
};

const WHATSAPP_MARK = 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z';

const DEFAULT_TEST_BODY = 'Test message from SteelERP — WhatsApp integration is working!';

/** The WhatsApp card: settings on the left, a test-message panel on the right. */
export default function WhatsAppPanel({ whatsapp, onSave, onTest, onSendTest, compact = false }) {
    const [enabled, setEnabled] = useState(whatsapp.enabled);
    const [instanceId, setInstanceId] = useState(whatsapp.instance_id);
    const [webhookPath, setWebhookPath] = useState(whatsapp.webhook_path);
    const [token, setToken] = useState('');
    const [secret, setSecret] = useState('');
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [connection, setConnection] = useState(null);
    const [testing, setTesting] = useState(false);

    const [to, setTo] = useState('');
    const [body, setBody] = useState(DEFAULT_TEST_BODY);
    const [sendState, setSendState] = useState(null);
    const [sending, setSending] = useState(false);
    const { showToast } = useToast();

    useEffect(() => {
        setEnabled(whatsapp.enabled);
        setInstanceId(whatsapp.instance_id);
        setWebhookPath(whatsapp.webhook_path);
        setToken('');
        setSecret('');
    }, [whatsapp]);

    async function save() {
        setSaving(true);
        setErrors({});
        try {
            await onSave({
                enabled, instance_id: instanceId, webhook_path: webhookPath,
                token: token || null, webhook_secret: secret || null,
            });
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err?.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (!err?.errors && err?.message) showToast(err.message, 'error');
        } finally {
            setSaving(false);
        }
    }

    async function test() {
        setTesting(true);
        setConnection(null);
        try {
            setConnection(await onTest());
        } catch (err) {
            setConnection({ ok: false, message: err?.message || 'Connection failed.' });
        } finally {
            setTesting(false);
        }
    }

    async function send() {
        setSending(true);
        setSendState(null);
        try {
            setSendState(await onSendTest({ to, body }));
        } catch (err) {
            const firstError = Object.values(err?.errors ?? {})[0]?.[0];
            setSendState({ ok: false, message: firstError || err?.message || 'Send failed.' });
        } finally {
            setSending(false);
        }
    }

    return (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e5e7eb', boxShadow: '0 1px 4px rgba(0,0,0,.06)', overflow: 'hidden' }}>
            <div style={{
                padding: '20px 28px', background: 'linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%)',
                borderBottom: '1px solid #d1fae5', display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap',
            }}>
                <div style={{
                    width: 44, height: 44, borderRadius: 12, background: '#22c55e', display: 'flex',
                    alignItems: 'center', justifyContent: 'center', flexShrink: 0, boxShadow: '0 4px 12px rgba(34,197,94,.35)',
                }}>
                    <svg style={{ width: 24, height: 24, color: '#fff' }} fill="currentColor" viewBox="0 0 24 24">
                        <path d={WHATSAPP_MARK} />
                    </svg>
                </div>
                <div>
                    <div style={{ fontSize: 17, fontWeight: 700, color: '#14532d' }}>WhatsApp (UltraMSG)</div>
                    <div style={{ fontSize: 13, color: '#16a34a', marginTop: 1 }}>
                        Send WhatsApp messages and notifications via UltraMSG
                    </div>
                </div>
                <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ fontSize: 12, color: enabled ? '#16a34a' : '#6b7280', fontWeight: 500 }}>
                        {enabled ? 'Enabled' : 'Disabled'}
                    </span>
                    <Switch on={enabled} onClick={() => setEnabled((prev) => !prev)} label="WhatsApp enabled" />
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: compact ? '1fr' : '1fr 360px' }}>
                <div style={{ padding: 28, borderRight: compact ? 'none' : '1px solid #e5e7eb' }}>
                    <div style={SECTION_LABEL}>Connection Settings</div>

                    <div style={{ marginBottom: 18 }}>
                        <label htmlFor="wa-instance-id" className="form-label">Instance ID</label>
                        <input
                            id="wa-instance-id" type="text" className="form-input" placeholder="e.g. instance177593"
                            value={instanceId} onChange={(e) => setInstanceId(e.target.value)}
                        />
                        {errors.instance_id && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.instance_id}</p>}
                    </div>

                    <SecretField
                        id="wa-token" label="API Token" value={token} onChange={setToken}
                        alreadySet={whatsapp.token_set} placeholder="Your UltraMSG token"
                    />
                    {errors.token && <p style={{ color: '#dc2626', fontSize: 12, marginTop: -12, marginBottom: 12 }}>{errors.token}</p>}

                    <SecretField
                        id="wa-webhook-secret" label="Webhook Secret" value={secret} onChange={setSecret}
                        alreadySet={whatsapp.webhook_secret_set} optional
                        placeholder="Leave empty to skip HMAC verification"
                    />

                    <div style={{ marginBottom: 28 }}>
                        <label htmlFor="wa-webhook-path" className="form-label">Webhook Path</label>
                        <div style={{ display: 'flex', alignItems: 'stretch' }}>
                            <span style={{
                                display: 'inline-flex', alignItems: 'center', padding: '0 12px', fontSize: 13,
                                color: '#6b7280', background: '#f9fafb', border: '1px solid #d1d5db',
                                borderRight: 'none', borderRadius: '6px 0 0 6px', whiteSpace: 'nowrap',
                            }}>
                                {whatsapp.base_url}/
                            </span>
                            <input
                                id="wa-webhook-path" type="text" className="form-input"
                                style={{ borderRadius: '0 6px 6px 0', flex: 1 }}
                                value={webhookPath} onChange={(e) => setWebhookPath(e.target.value)}
                            />
                        </div>
                        <p style={{ fontSize: 12, color: '#6b7280', marginTop: 5 }}>
                            Full webhook URL: <code style={{ background: '#f3f4f6', padding: '1px 6px', borderRadius: 4 }}>
                                {whatsapp.base_url}/{webhookPath}
                            </code>
                        </p>
                    </div>

                    <div style={{ display: 'flex', alignItems: 'center', gap: 12, paddingTop: 20, borderTop: '1px solid #f3f4f6', flexWrap: 'wrap' }}>
                        <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                            {saving ? 'Saving…' : 'Save Settings'}
                        </button>
                        <button
                            type="button" onClick={test} disabled={testing}
                            style={{
                                display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 13, color: '#2563eb',
                                background: 'none', border: '1px solid #dbeafe', borderRadius: 8, padding: '8px 14px',
                                cursor: 'pointer', fontWeight: 500,
                            }}
                        >
                            <svg style={{ width: 14, height: 14 }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {testing ? 'Testing…' : 'Test Connection'}
                        </button>
                        {connection && (
                            <span style={{ fontSize: 13, color: connection.ok ? '#16a34a' : '#dc2626' }}>{connection.message}</span>
                        )}
                    </div>
                </div>

                <div style={{ padding: 28, background: '#f8fafc' }}>
                    <div style={SECTION_LABEL}>Send Test Message</div>

                    <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 12, padding: 20 }}>
                        <p style={{ fontSize: 13, color: '#6b7280', margin: '0 0 18px' }}>
                            Verify the connection works end-to-end by sending a real WhatsApp message.
                        </p>

                        <div style={{ marginBottom: 14 }}>
                            <label htmlFor="wa-test-to" className="form-label">Phone Number</label>
                            <input
                                id="wa-test-to" type="text" className="form-input" placeholder="+97333165444"
                                value={to} onChange={(e) => setTo(e.target.value)}
                            />
                        </div>
                        <div style={{ marginBottom: 18 }}>
                            <label htmlFor="wa-test-body" className="form-label">Message</label>
                            <textarea
                                id="wa-test-body" rows={4} className="form-input" style={{ resize: 'vertical' }}
                                value={body} onChange={(e) => setBody(e.target.value)}
                            />
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                            {/* This sends a real message to a real phone, so it is
                                never wired to Enter — only to a deliberate click. */}
                            <button
                                type="button" onClick={send} disabled={sending || !to}
                                style={{
                                    display: 'inline-flex', alignItems: 'center', gap: 7, padding: '9px 18px',
                                    fontSize: 13, fontWeight: 600, color: '#fff',
                                    background: (sending || !to) ? '#86efac' : '#22c55e',
                                    border: 'none', borderRadius: 8, cursor: (sending || !to) ? 'default' : 'pointer',
                                }}
                            >
                                <svg style={{ width: 14, height: 14 }} fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                                </svg>
                                {sending ? 'Sending…' : 'Send Message'}
                            </button>
                            {sendState && (
                                <span style={{ fontSize: 12, color: sendState.ok ? '#16a34a' : '#dc2626' }}>{sendState.message}</span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
