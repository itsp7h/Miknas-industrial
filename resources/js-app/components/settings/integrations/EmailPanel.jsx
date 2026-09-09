import { useState } from 'react';
import Switch from './Switch';

const SECTION_LABEL = {
    fontSize: 12, fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase',
    letterSpacing: '.06em', marginBottom: 20,
};

const TYPE_STYLE = {
    azure: { label: 'Microsoft 365', background: '#eff6ff', colour: '#2563eb', icon: '✉️' },
    smtp: { label: 'SMTP', background: '#f0fdf4', colour: '#16a34a', icon: '📧' },
};

function AccountRow({ account, onToggle, onEdit, onDelete }) {
    const type = TYPE_STYLE[account.type] ?? TYPE_STYLE.smtp;

    return (
        <div style={{
            padding: '16px 28px', display: 'flex', alignItems: 'center', gap: 14,
            borderBottom: '1px solid #f3f4f6', flexWrap: 'wrap',
        }}>
            <div style={{
                width: 38, height: 38, background: type.background, borderRadius: 10, display: 'flex',
                alignItems: 'center', justifyContent: 'center', flexShrink: 0, fontSize: 17,
            }}>
                {type.icon}
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', marginBottom: 3 }}>
                    <span style={{ fontSize: 14, fontWeight: 600, color: '#111827' }}>{account.label}</span>
                    <code style={{ fontSize: 11, color: '#6b7280', background: '#f3f4f6', padding: '2px 7px', borderRadius: 5 }}>
                        {account.name}
                    </code>
                    <span style={{
                        fontSize: 11, fontWeight: 600, color: type.colour, background: type.background,
                        padding: '2px 9px', borderRadius: 999,
                    }}>
                        {type.label}
                    </span>
                </div>
                <div style={{ fontSize: 12, color: '#6b7280' }}>{account.from_address}</div>
            </div>
            <Switch on={account.enabled} onClick={() => onToggle(account)} label={`${account.label} enabled`} />
            <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                <button
                    type="button" onClick={() => onEdit(account)}
                    style={{ fontSize: 12, color: '#374151', background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: 6, padding: '5px 12px', cursor: 'pointer', fontWeight: 500 }}
                >
                    Edit
                </button>
                <button
                    type="button" onClick={() => onDelete(account)}
                    style={{ fontSize: 12, color: '#dc2626', background: '#fff5f5', border: '1px solid #fecaca', borderRadius: 6, padding: '5px 12px', cursor: 'pointer', fontWeight: 500 }}
                >
                    Delete
                </button>
            </div>
        </div>
    );
}

/** The Email card: the account list on the left, a test-email panel on the right. */
export default function EmailPanel({ accounts, onAdd, onEdit, onToggle, onDelete, onSendTest, compact = false }) {
    const [accountId, setAccountId] = useState('');
    const [to, setTo] = useState('');
    const [state, setState] = useState(null);
    const [sending, setSending] = useState(false);

    async function send() {
        setSending(true);
        setState(null);
        try {
            setState(await onSendTest(accountId, to));
        } catch (err) {
            const firstError = Object.values(err?.errors ?? {})[0]?.[0];
            setState({ ok: false, message: firstError || err?.message || 'Send failed.' });
        } finally {
            setSending(false);
        }
    }

    return (
        <div style={{ background: '#fff', borderRadius: 16, border: '1px solid #e5e7eb', boxShadow: '0 1px 4px rgba(0,0,0,.06)', overflow: 'hidden' }}>
            <div style={{
                padding: '20px 28px', background: 'linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%)',
                borderBottom: '1px solid #bfdbfe', display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap',
            }}>
                <div style={{
                    width: 44, height: 44, borderRadius: 12, background: '#2563eb', display: 'flex',
                    alignItems: 'center', justifyContent: 'center', flexShrink: 0, fontSize: 22,
                    boxShadow: '0 4px 12px rgba(37,99,235,.35)',
                }}>
                    ✉️
                </div>
                <div>
                    <div style={{ fontSize: 17, fontWeight: 700, color: '#1e3a8a' }}>Email Accounts</div>
                    <div style={{ fontSize: 13, color: '#2563eb', marginTop: 1 }}>
                        Manage mail accounts for sending notifications and reports
                    </div>
                </div>
                <div style={{ marginLeft: 'auto' }}>
                    <button
                        type="button" onClick={onAdd} className="btn-primary"
                        style={{ display: 'inline-flex', alignItems: 'center', gap: 7, padding: '9px 18px' }}
                    >
                        <span style={{ fontSize: 17, lineHeight: 1, fontWeight: 400 }}>+</span> Add Account
                    </button>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: compact ? '1fr' : '1fr 320px' }}>
                <div style={{ borderRight: compact ? 'none' : '1px solid #e5e7eb' }}>
                    <div style={{
                        padding: '16px 28px', borderBottom: '1px solid #f3f4f6', display: 'flex',
                        alignItems: 'center', justifyContent: 'space-between',
                    }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '.06em' }}>
                            Configured Accounts
                        </div>
                        <div style={{ fontSize: 12, color: '#6b7280' }}>
                            {accounts.length} account{accounts.length === 1 ? '' : 's'}
                        </div>
                    </div>

                    {accounts.map((account) => (
                        <AccountRow
                            key={account.id} account={account}
                            onToggle={onToggle} onEdit={onEdit} onDelete={onDelete}
                        />
                    ))}

                    {accounts.length === 0 && (
                        <div style={{ padding: '48px 28px', textAlign: 'center' }}>
                            <div style={{
                                width: 56, height: 56, borderRadius: 16, background: '#eff6ff', display: 'flex',
                                alignItems: 'center', justifyContent: 'center', fontSize: 26, margin: '0 auto 16px',
                            }}>
                                ✉️
                            </div>
                            <div style={{ fontSize: 15, fontWeight: 600, color: '#374151', marginBottom: 6 }}>No mail accounts yet</div>
                            <div style={{ fontSize: 13, color: '#6b7280', marginBottom: 20 }}>
                                Add an account to start sending emails via Microsoft 365 or SMTP.
                            </div>
                            <button
                                type="button" onClick={onAdd}
                                style={{
                                    display: 'inline-flex', alignItems: 'center', gap: 6, padding: '9px 18px',
                                    fontSize: 13, fontWeight: 600, color: '#fff', background: '#2563eb',
                                    border: 'none', borderRadius: 8, cursor: 'pointer',
                                }}
                            >
                                + Add Your First Account
                            </button>
                        </div>
                    )}
                </div>

                <div style={{ padding: 28, background: '#f8fafc' }}>
                    <div style={SECTION_LABEL}>Send Test Email</div>

                    <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: 12, padding: 20 }}>
                        <p style={{ fontSize: 13, color: '#6b7280', margin: '0 0 18px' }}>
                            Send a test email via any of your configured accounts.
                        </p>

                        <div style={{ marginBottom: 14 }}>
                            <label htmlFor="em-test-account" className="form-label">Account</label>
                            <select
                                id="em-test-account" className="form-input"
                                value={accountId} onChange={(e) => setAccountId(e.target.value)}
                            >
                                <option value="">— select account —</option>
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>{account.label}</option>
                                ))}
                            </select>
                        </div>
                        <div style={{ marginBottom: 18 }}>
                            <label htmlFor="em-test-to" className="form-label">Recipient</label>
                            <input
                                id="em-test-to" type="text" className="form-input" placeholder="recipient@example.com"
                                value={to} onChange={(e) => setTo(e.target.value)}
                            />
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                            {/* A real email to a real address: click only, and only
                                once an account and a recipient are chosen. */}
                            <button
                                type="button" onClick={send} disabled={sending || !accountId || !to}
                                style={{
                                    display: 'inline-flex', alignItems: 'center', gap: 7, padding: '9px 18px',
                                    fontSize: 13, fontWeight: 600, color: '#fff',
                                    background: (sending || !accountId || !to) ? '#93c5fd' : '#2563eb',
                                    border: 'none', borderRadius: 8,
                                    cursor: (sending || !accountId || !to) ? 'default' : 'pointer',
                                }}
                            >
                                <svg style={{ width: 14, height: 14 }} fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                                </svg>
                                {sending ? 'Sending…' : 'Send'}
                            </button>
                            {state && (
                                <span style={{ fontSize: 12, color: state.ok ? '#16a34a' : '#dc2626' }}>{state.message}</span>
                            )}
                        </div>
                    </div>

                    <div style={{ marginTop: 16, padding: 14, background: '#fff', border: '1px solid #e5e7eb', borderRadius: 12 }}>
                        <div style={{ fontSize: 11, fontWeight: 600, color: '#9ca3af', marginBottom: 8 }}>USE IN CODE</div>
                        <code style={{ fontSize: 12, color: '#374151', lineHeight: 1.6 }}>Mail::mailer(&apos;account-name&apos;)</code>
                    </div>
                </div>
            </div>
        </div>
    );
}
