import { useEffect, useState } from 'react';
import Modal from '../../ui/Modal';
import SecretField from './SecretField';
import { apiGet } from '../../../api/client';

const SECTION_LABEL = {
    fontSize: 11, fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase',
    letterSpacing: '.05em', marginBottom: 12,
};

const EMPTY = {
    name: '', label: '', type: 'smtp', from_address: '', from_name: '',
    tenant_id: '', client_id: '', client_secret: '',
    host: '', port: 587, encryption: 'tls', username: '', password: '',
};

/** Blade slugified the account name as you typed, since it is used in code. */
const slugify = (value) => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

export default function MailAccountModal({ open, account, onClose, onSave, onTest }) {
    const [values, setValues] = useState(EMPTY);
    const [secretsSet, setSecretsSet] = useState({ client_secret: false, password: false });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [testState, setTestState] = useState(null);
    const [testing, setTesting] = useState(false);

    // An existing account's non-secret config is fetched on open; its secrets
    // never leave the server, so those fields start blank.
    useEffect(() => {
        if (!open) return;
        setErrors({});
        setTestState(null);

        if (!account) {
            setValues(EMPTY);
            setSecretsSet({ client_secret: false, password: false });

            return;
        }

        apiGet(`/settings/mail-accounts/${account.id}`)
            .then((response) => {
                const data = response.data;
                const config = data.config ?? {};
                setValues({
                    ...EMPTY,
                    name: data.name, label: data.label, type: data.type,
                    from_address: data.from_address, from_name: data.from_name ?? '',
                    tenant_id: config.tenant_id ?? '', client_id: config.client_id ?? '',
                    host: config.host ?? '', port: config.port ?? 587,
                    encryption: config.encryption ?? 'tls', username: config.username ?? '',
                });
                setSecretsSet(data.secrets_set ?? { client_secret: false, password: false });
            })
            .catch(() => setErrors({ name: 'Could not load that account.' }));
    }, [open, account]);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    function payload() {
        const config = values.type === 'azure'
            ? { tenant_id: values.tenant_id, client_id: values.client_id, client_secret: values.client_secret }
            : {
                host: values.host, port: Number(values.port), encryption: values.encryption,
                username: values.username, password: values.password,
            };

        return {
            name: values.name, label: values.label, type: values.type,
            from_address: values.from_address, from_name: values.from_name || null,
            config, enabled: account ? account.enabled : true,
        };
    }

    async function save() {
        setSaving(true);
        setErrors({});
        try {
            await onSave(account, payload());
            onClose();
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err?.errors ?? {}).map(([key, messages]) => [key.replace('config.', ''), messages[0]])
            ));
            if (!err?.errors && err?.message) setErrors({ name: err.message });
        } finally {
            setSaving(false);
        }
    }

    async function test() {
        if (!account) {
            setTestState({ ok: false, message: 'Save the account first, then test it.' });

            return;
        }
        setTesting(true);
        setTestState(null);
        try {
            setTestState(await onTest(account));
        } catch (err) {
            setTestState({ ok: false, message: err?.message || 'Connection failed.' });
        } finally {
            setTesting(false);
        }
    }

    const isAzure = values.type === 'azure';

    return (
        <Modal open={open} title={account ? 'Edit Mail Account' : 'Add Mail Account'} onClose={onClose}>
            <div style={{ marginBottom: 16 }}>
                <label htmlFor="ma-name" className="form-label">
                    Account Name <span style={{ color: '#9ca3af', fontWeight: 400 }}>(used in code)</span>
                </label>
                <input
                    id="ma-name" type="text" className="form-input" placeholder="e.g. customer-support"
                    value={values.name} onChange={(e) => setField('name', slugify(e.target.value))}
                />
                <div style={{ fontSize: 11, color: '#9ca3af', marginTop: 3 }}>Lowercase letters, numbers and hyphens only.</div>
                {errors.name && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.name}</p>}
            </div>

            <div style={{ marginBottom: 16 }}>
                <label htmlFor="ma-label" className="form-label">Label</label>
                <input
                    id="ma-label" type="text" className="form-input" placeholder="e.g. Customer Support"
                    value={values.label} onChange={(e) => setField('label', e.target.value)}
                />
                {errors.label && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.label}</p>}
            </div>

            <div style={{ marginBottom: 20 }}>
                <label htmlFor="ma-type" className="form-label">Type</label>
                <select
                    id="ma-type" className="form-input"
                    value={values.type} onChange={(e) => setField('type', e.target.value)}
                >
                    <option value="smtp">📧 SMTP</option>
                    <option value="azure">✉️ Microsoft 365 (Azure AD)</option>
                </select>
            </div>

            {isAzure ? (
                <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: 16, marginBottom: 16 }}>
                    <div style={SECTION_LABEL}>Azure AD Credentials</div>
                    <div style={{ marginBottom: 12 }}>
                        <label htmlFor="ma-tenant" className="form-label">Tenant ID</label>
                        <input
                            id="ma-tenant" type="text" className="form-input" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            value={values.tenant_id} onChange={(e) => setField('tenant_id', e.target.value)}
                        />
                        {errors.tenant_id && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.tenant_id}</p>}
                    </div>
                    <div style={{ marginBottom: 12 }}>
                        <label htmlFor="ma-client" className="form-label">Client ID</label>
                        <input
                            id="ma-client" type="text" className="form-input" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            value={values.client_id} onChange={(e) => setField('client_id', e.target.value)}
                        />
                        {errors.client_id && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.client_id}</p>}
                    </div>
                    <SecretField
                        id="ma-client-secret" label="Client Secret"
                        value={values.client_secret} onChange={(v) => setField('client_secret', v)}
                        alreadySet={secretsSet.client_secret} placeholder="Your Azure AD client secret"
                    />
                    {errors.client_secret && <p style={{ color: '#dc2626', fontSize: 12, marginTop: -12, marginBottom: 12 }}>{errors.client_secret}</p>}
                </div>
            ) : (
                <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: 16, marginBottom: 16 }}>
                    <div style={SECTION_LABEL}>SMTP Server</div>
                    <div style={{ display: 'flex', gap: 10, marginBottom: 12, flexWrap: 'wrap' }}>
                        <div style={{ flex: 1, minWidth: 140 }}>
                            <label htmlFor="ma-host" className="form-label">Host</label>
                            <input
                                id="ma-host" type="text" className="form-input" placeholder="smtp.gmail.com"
                                value={values.host} onChange={(e) => setField('host', e.target.value)}
                            />
                        </div>
                        <div style={{ width: 80 }}>
                            <label htmlFor="ma-port" className="form-label">Port</label>
                            <input
                                id="ma-port" type="number" className="form-input"
                                value={values.port} onChange={(e) => setField('port', e.target.value)}
                            />
                        </div>
                        <div style={{ width: 100 }}>
                            <label htmlFor="ma-encryption" className="form-label">Encryption</label>
                            <select
                                id="ma-encryption" className="form-input"
                                value={values.encryption} onChange={(e) => setField('encryption', e.target.value)}
                            >
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                    {errors.host && <p style={{ color: '#dc2626', fontSize: 12, marginBottom: 8 }}>{errors.host}</p>}
                    <div style={{ marginBottom: 12 }}>
                        <label htmlFor="ma-username" className="form-label">Username</label>
                        <input
                            id="ma-username" type="text" className="form-input" placeholder="user@example.com"
                            value={values.username} onChange={(e) => setField('username', e.target.value)}
                        />
                    </div>
                    <SecretField
                        id="ma-password" label="Password"
                        value={values.password} onChange={(v) => setField('password', v)}
                        alreadySet={secretsSet.password} placeholder="SMTP password or app password"
                    />
                </div>
            )}

            <div style={{ borderTop: '1px solid #f3f4f6', paddingTop: 16, marginBottom: 16 }}>
                <div style={SECTION_LABEL}>Sender</div>
                <div style={{ marginBottom: 12 }}>
                    <label htmlFor="ma-from-address" className="form-label">From Address</label>
                    <input
                        id="ma-from-address" type="text" className="form-input" placeholder="noreply@yourdomain.com"
                        value={values.from_address} onChange={(e) => setField('from_address', e.target.value)}
                    />
                    {errors.from_address && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.from_address}</p>}
                </div>
                <div>
                    <label htmlFor="ma-from-name" className="form-label">
                        From Name <span style={{ color: '#9ca3af', fontWeight: 400 }}>(optional)</span>
                    </label>
                    <input
                        id="ma-from-name" type="text" className="form-input" placeholder="SteelERP"
                        value={values.from_name} onChange={(e) => setField('from_name', e.target.value)}
                    />
                </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap', marginTop: 20 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <button
                        type="button" onClick={test} disabled={testing}
                        style={{ fontSize: 13, color: '#2563eb', background: 'none', border: 'none', cursor: 'pointer', textDecoration: 'underline', padding: 0 }}
                    >
                        🔗 {testing ? 'Testing…' : 'Test Connection'}
                    </button>
                    {testState && (
                        <span style={{ fontSize: 12, color: testState.ok ? '#16a34a' : '#dc2626' }}>{testState.message}</span>
                    )}
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
                    <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                        {saving ? 'Saving…' : 'Save Account'}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
