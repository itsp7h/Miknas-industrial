import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import WhatsAppPanel from './WhatsAppPanel';
import { ToastProvider } from '../../ui/Toast';

/**
 * The panel owns its form. It seeds from `whatsapp` once, when it mounts, and
 * never again from the prop: re-seeding whenever that object's identity
 * changed threw away what the user had just changed (CLAUDE.md gotcha #10).
 * It is also why `IntegrationsPage > flips the enabled switch` failed at
 * random on CI: the click could land before the mount effect ran, which then
 * put the switch back.
 */
const WHATSAPP = {
    enabled: true, instance_id: 'instance177593', webhook_path: 'ultra-message/webhook',
    token_set: true, webhook_secret_set: false, base_url: 'http://localhost:8001',
};

const panel = (props) => (
    <ToastProvider>
        <WhatsAppPanel whatsapp={WHATSAPP} onSave={vi.fn()} onTest={vi.fn()} onSendTest={vi.fn()} {...props} />
    </ToastProvider>
);

describe('WhatsAppPanel', () => {
    it('keeps an unsaved change when the parent hands it a fresh copy of the same settings', () => {
        const { rerender } = render(panel());

        fireEvent.click(screen.getByLabelText('WhatsApp enabled'));
        fireEvent.change(screen.getByLabelText('Instance ID'), { target: { value: 'instance999' } });
        expect(screen.getByText('Disabled')).toBeInTheDocument();

        // A re-render or a re-fetch: same values, new object.
        rerender(panel({ whatsapp: { ...WHATSAPP } }));

        expect(screen.getByText('Disabled')).toBeInTheDocument();
        expect(screen.getByLabelText('Instance ID')).toHaveValue('instance999');
    });

    it('clears the token field after a save, and shows what was saved', async () => {
        const onSave = vi.fn().mockResolvedValue({ ...WHATSAPP, enabled: false, instance_id: 'instance-saved' });
        render(panel({ onSave }));

        fireEvent.change(screen.getByLabelText('API Token'), { target: { value: 'new-secret-token' } });
        fireEvent.click(screen.getByLabelText('WhatsApp enabled'));
        fireEvent.click(screen.getByText('Save Settings'));

        await waitFor(() => expect(onSave).toHaveBeenCalledWith(expect.objectContaining({
            enabled: false, token: 'new-secret-token',
        })));
        await waitFor(() => expect(screen.getByLabelText('API Token')).toHaveValue(''));
        expect(screen.getByLabelText('Instance ID')).toHaveValue('instance-saved');
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });
});
