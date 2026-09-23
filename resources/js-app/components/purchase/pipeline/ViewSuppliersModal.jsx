import { useState } from 'react';
import Modal from '../../ui/Modal';

const CHANNEL_BADGE = {
    email: { label: 'Email', background: '#eff6ff', colour: '#2563eb' },
    whatsapp: { label: 'WhatsApp', background: '#f0fdf4', colour: '#15803d' },
    both: { label: 'Email + WA', background: '#fef3c7', colour: '#92400e' },
};

// Tailwind JIT never sees classes used only inside a modal (CLAUDE.md #1), so
// the callout is inline styles like the badges above it.
const ACCOUNTABILITY_BLOCK = {
    display: 'flex', flexWrap: 'wrap', gap: '4px 16px', alignItems: 'baseline',
    marginTop: 8, padding: '7px 10px',
    background: '#f8fafc', border: '1px solid #e2e8f0', borderLeft: '3px solid #2563eb',
    borderRadius: 8,
};

const ACTOR_LABEL = {
    fontSize: 10, fontWeight: 700, color: '#94a3b8',
    textTransform: 'uppercase', letterSpacing: '.05em',
};

const ACTOR_NAME = { fontSize: 12.5, fontWeight: 700, color: '#0f172a' };

const STATUS_BADGE = {
    pending: { label: 'Unsent', background: '#fef3c7', colour: '#92400e' },
    sent: { label: 'Sent', background: '#eff6ff', colour: '#2563eb' },
    submitted: { label: 'Quoted', background: '#f0fdf4', colour: '#15803d' },
};

/**
 * Blade's view-rfq modal: who was invited, on what channel, where each one
 * stands — and the supplier's own portal link, which is the fallback when an
 * invitation cannot be delivered automatically.
 */
export default function ViewSuppliersModal({ open, request, onClose, onSend }) {
    const [copied, setCopied] = useState(null);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');

    const invitations = request?.rfq_invitations ?? [];
    const pending = request?.pending_invitation_count ?? 0;

    async function send() {
        setSending(true);
        setError('');
        try {
            await onSend();
            onClose();
        } catch (err) {
            setError(err?.message || 'Could not send the invitations.');
        } finally {
            setSending(false);
        }
    }

    function copy(url, id) {
        navigator.clipboard?.writeText(url)
            .then(() => setCopied(id))
            .catch(() => setError('Could not copy that link — select and copy it manually.'));
    }

    return (
        <Modal open={open} title={`Suppliers — ${request?.request_number ?? ''}`} onClose={onClose}>
            {invitations.length === 0 && (
                <p style={{ fontSize: 13, color: '#64748b' }}>No suppliers selected yet.</p>
            )}

            {invitations.map((invitation) => {
                const channel = CHANNEL_BADGE[invitation.channel] ?? CHANNEL_BADGE.email;
                const status = STATUS_BADGE[invitation.status] ?? { label: invitation.status, background: '#f1f5f9', colour: '#64748b' };

                return (
                    <div key={invitation.id} style={{ border: '1px solid #e2e8f0', borderRadius: 10, padding: 12, marginBottom: 8 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <span style={{ fontSize: 13, fontWeight: 600, color: '#0f172a', flex: 1, minWidth: 0 }}>
                                {invitation.supplier_name}
                            </span>
                            <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 8, background: channel.background, color: channel.colour }}>
                                {channel.label}
                            </span>
                            <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 8, background: status.background, color: status.colour }}>
                                {status.label}
                            </span>
                        </div>

                        {/* Who decided, the way an awarded line names who awarded it.
                            Given its own tinted block rather than grey small print:
                            this is the answer to "who picked this supplier?", and it
                            is the reason most people open this modal at all. An
                            invitation from before this was recorded says nothing
                            rather than inventing a name. */}
                        {(invitation.selected_by || invitation.sent_by) && (
                            <div style={ACCOUNTABILITY_BLOCK}>
                                {invitation.selected_by && (
                                    <span style={{ display: 'inline-flex', alignItems: 'baseline', gap: 5 }}>
                                        <span style={ACTOR_LABEL}>Selected by</span>
                                        <span style={ACTOR_NAME}>{invitation.selected_by}</span>
                                    </span>
                                )}
                                {invitation.sent_by && (
                                    <span style={{ display: 'inline-flex', alignItems: 'baseline', gap: 5 }}>
                                        <span style={ACTOR_LABEL}>Sent by</span>
                                        <span style={ACTOR_NAME}>{invitation.sent_by}</span>
                                        {invitation.sent_at && (
                                            <span style={{ fontSize: 11, color: '#64748b' }}>· {invitation.sent_at}</span>
                                        )}
                                    </span>
                                )}
                            </div>
                        )}

                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8, flexWrap: 'wrap' }}>
                            <button type="button" onClick={() => copy(invitation.portal_url, invitation.id)} className="btn-secondary btn-sm">
                                {copied === invitation.id ? '✓ Link copied' : 'Copy quote link'}
                            </button>
                            {invitation.whatsapp_link && (
                                <a href={invitation.whatsapp_link} target="_blank" rel="noreferrer" className="btn-secondary btn-sm" style={{ textDecoration: 'none' }}>
                                    Open in WhatsApp
                                </a>
                            )}
                        </div>
                    </div>
                );
            })}

            {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 10 }}>{error}</p>}

            <div className="mt-5 flex items-center justify-end gap-3">
                <button type="button" onClick={onClose} className="btn-secondary">Close</button>
                {/* Sending is what moves the request to the quoting stage, so it is
                    offered here rather than only on the timeline. */}
                {pending > 0 && onSend && (
                    <button type="button" onClick={send} className="btn-success" disabled={sending}>
                        {sending ? 'Sending…' : `Send ${pending} invitation(s)`}
                    </button>
                )}
            </div>
        </Modal>
    );
}
