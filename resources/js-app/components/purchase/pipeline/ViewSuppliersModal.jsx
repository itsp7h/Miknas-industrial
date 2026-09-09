import { useState } from 'react';
import Modal from '../../ui/Modal';

const CHANNEL_BADGE = {
    email: { label: 'Email', background: '#eff6ff', colour: '#2563eb' },
    whatsapp: { label: 'WhatsApp', background: '#f0fdf4', colour: '#15803d' },
    both: { label: 'Email + WA', background: '#fef3c7', colour: '#92400e' },
};

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
