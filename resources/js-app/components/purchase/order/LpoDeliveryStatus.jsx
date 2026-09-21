import { useState } from 'react';
import { apiPost } from '../../../api/client';
import { useToast } from '../../ui/Toast';

const WHEN = { dateStyle: 'medium', timeStyle: 'short' };

/**
 * Whether the supplier actually has this LPO, and the button to try again if
 * not.
 *
 * `status` is no answer to that question: an order is created reading 'sent'
 * the moment the pipeline generates it, which is exactly why the send is
 * recorded separately. This reads `sent_at`, and nothing else.
 *
 * The pipeline emails on issue, so the usual state here is "sent". The button
 * matters when that failed — no mail account configured, a bad address, SMTP
 * down — because the alternative recovery, re-generating the LPO, is refused
 * once goods or an invoice are recorded against it.
 */
export default function LpoDeliveryStatus({ order, onSent, compact = false }) {
    const [sending, setSending] = useState(false);
    const { showToast } = useToast();

    if (!order) return null;

    const sent = !!order.sent_at;
    const email = order.supplier?.email ?? null;

    async function send() {
        setSending(true);
        try {
            const response = await apiPost(`/purchase/orders/${order.id}/send`);
            onSent?.(response.data);
            showToast(response.message ?? 'LPO emailed to the supplier.', 'success');
        } catch (err) {
            showToast(err?.message || 'Could not email the LPO.', 'error');
        } finally {
            setSending(false);
        }
    }

    return (
        <div style={{
            maxWidth: 820, margin: '0 auto 16px', padding: compact ? '12px 14px' : '14px 18px',
            background: sent ? '#f0fdf4' : '#fffbeb',
            border: `1px solid ${sent ? '#bbf7d0' : '#fde68a'}`,
            borderRadius: 12, display: 'flex', alignItems: 'center',
            justifyContent: 'space-between', gap: 12, flexWrap: 'wrap',
        }}>
            <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 12.5, fontWeight: 600, color: sent ? '#15803d' : '#b45309' }}>
                    {sent ? '✓ Sent to supplier' : '⚠ Not sent to the supplier yet'}
                </div>
                <div style={{ fontSize: 11.5, color: '#64748b', marginTop: 2 }}>
                    {sent
                        ? `Emailed to ${order.sent_to} on ${new Date(order.sent_at).toLocaleString(undefined, WHEN)}.`
                        : email
                            ? `Nothing has reached ${email} for this order.`
                            : 'This supplier has no email address on file — add one, then send.'}
                </div>
            </div>

            <button
                type="button"
                onClick={send}
                disabled={sending || !email}
                className={sent ? 'btn-secondary btn-sm' : 'btn-primary btn-sm'}
                style={{ flexShrink: 0 }}
            >
                {sending ? 'Sending…' : sent ? '↻ Send again' : '✉ Send to supplier'}
            </button>
        </div>
    );
}
