import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import IntegrationsPage from './IntegrationsPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const WHATSAPP = {
    enabled: true, instance_id: 'instance177593', webhook_path: 'ultra-message/webhook',
    token_set: true, webhook_secret_set: false, base_url: 'http://localhost:8001',
};

const ACCOUNTS = [
    { id: 1, name: 'support', label: 'Customer Support', type: 'smtp', from_address: 'noreply@example.test', from_name: 'SteelERP', enabled: true },
    { id: 2, name: 'reports', label: 'Reports', type: 'azure', from_address: 'reports@example.test', from_name: null, enabled: false },
];

function mockGet(overrides = {}) {
    vi.spyOn(client, 'apiGet').mockImplementation((url) => {
        if (url === '/settings/integrations/whatsapp') return Promise.resolve({ ...WHATSAPP, ...overrides.whatsapp });
        if (url === '/settings/mail-accounts') return Promise.resolve({ data: overrides.accounts ?? ACCOUNTS });
        if (url.startsWith('/settings/mail-accounts/')) {
            return Promise.resolve({
                data: {
                    ...ACCOUNTS[0],
                    config: { host: 'smtp.example.test', port: 587, encryption: 'tls', username: 'user@example.test' },
                    secrets_set: { password: true, client_secret: false },
                },
            });
        }

        return Promise.resolve({});
    });
}

const wrap = () => render(<ToastProvider><IntegrationsPage /></ToastProvider>);

describe('settings IntegrationsPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        mockGet();
    });

    it('opens on the WhatsApp tab with its settings loaded', async () => {
        wrap();

        expect(await screen.findByText('Settings — Integrations')).toBeInTheDocument();
        expect(screen.getByText('WhatsApp (UltraMSG)')).toBeInTheDocument();
        expect(screen.getByLabelText('Instance ID')).toHaveValue('instance177593');
        expect(screen.getByText('Enabled')).toBeInTheDocument();
        // The webhook URL is shown as the base plus the path.
        expect(screen.getByText('http://localhost:8001/ultra-message/webhook')).toBeInTheDocument();
    });

    // The Blade page put the live token into an input's value; a stored secret
    // never reaches the browser now, so the field starts blank and says so.
    it('starts the token field blank when one is already stored', async () => {
        wrap();

        const token = await screen.findByLabelText('API Token');
        expect(token).toHaveValue('');
        expect(token).toHaveAttribute('placeholder', 'Stored — leave blank to keep it');
        expect(token).toHaveAttribute('type', 'password');
    });

    it('reveals the token with the eye button', async () => {
        wrap();

        fireEvent.click(await screen.findByLabelText('Show API Token'));
        expect(screen.getByLabelText('API Token')).toHaveAttribute('type', 'text');
    });

    it('saves the WhatsApp settings, sending null for an untouched secret', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue(WHATSAPP);
        wrap();

        await screen.findByLabelText('Instance ID');
        fireEvent.change(screen.getByLabelText('Instance ID'), { target: { value: 'instance999' } });
        fireEvent.click(screen.getByText('Save Settings'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/integrations/whatsapp', {
            enabled: true, instance_id: 'instance999', webhook_path: 'ultra-message/webhook',
            token: null, webhook_secret: null,
        }));
    });

    it('flips the enabled switch without saving until Save is pressed', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ ...WHATSAPP, enabled: false });
        wrap();

        fireEvent.click(await screen.findByLabelText('WhatsApp enabled'));
        expect(screen.getByText('Disabled')).toBeInTheDocument();
        expect(put).not.toHaveBeenCalled();

        fireEvent.click(screen.getByText('Save Settings'));
        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/integrations/whatsapp', expect.objectContaining({ enabled: false })));
    });

    // Both test endpoints answer 200 with success:false, so the body decides.
    it('reports a failed connection test in place', async () => {
        vi.spyOn(client, 'apiPost').mockResolvedValue({ success: false, message: 'Invalid token' });
        wrap();

        fireEvent.click(await screen.findByText('Test Connection'));
        expect(await screen.findByText('Invalid token')).toBeInTheDocument();
    });

    it('reports a successful connection test in place', async () => {
        vi.spyOn(client, 'apiPost').mockResolvedValue({ success: true, status: { accountStatus: 'authenticated' } });
        wrap();

        fireEvent.click(await screen.findByText('Test Connection'));
        expect(await screen.findByText('Connected — authenticated')).toBeInTheDocument();
    });

    // Sending is a real WhatsApp message, so the button stays disabled until a
    // number is entered and it is never wired to Enter.
    it('keeps Send Message disabled until a number is entered', async () => {
        wrap();

        const send = await screen.findByText('Send Message');
        expect(send).toBeDisabled();
        fireEvent.change(screen.getByLabelText('Phone Number'), { target: { value: '+97300000000' } });
        expect(send).not.toBeDisabled();
    });

    it('switches to the Email tab and lists the accounts', async () => {
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));

        expect(await screen.findByText('Email Accounts')).toBeInTheDocument();
        // Once in its row, once in the test-email account picker.
        expect(screen.getAllByText('Customer Support')).toHaveLength(2);
        expect(screen.getByText('support')).toBeInTheDocument();
        expect(screen.getByText('SMTP')).toBeInTheDocument();
        expect(screen.getByText('Microsoft 365')).toBeInTheDocument();
        expect(screen.getByText('2 accounts')).toBeInTheDocument();
    });

    it('shows the empty state when there are no accounts', async () => {
        mockGet({ accounts: [] });
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        expect(await screen.findByText('No mail accounts yet')).toBeInTheDocument();
        expect(screen.getByText('+ Add Your First Account')).toBeInTheDocument();
    });

    it('toggles an account immediately', async () => {
        const patch = vi.spyOn(client, 'apiPatch').mockResolvedValue({ data: { ...ACCOUNTS[0], enabled: false } });
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        fireEvent.click(await screen.findByLabelText('Customer Support enabled'));

        await waitFor(() => expect(patch).toHaveBeenCalledWith('/settings/mail-accounts/1/toggle'));
    });

    it('warns what deleting a mail account breaks', async () => {
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        fireEvent.click((await screen.findAllByText('Delete'))[0]);

        expect(await screen.findByText(/Mail::mailer\('support'\) will stop working/)).toBeInTheDocument();
    });

    it('opens the edit modal prefilled, with the password left blank', async () => {
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        fireEvent.click((await screen.findAllByText('Edit'))[0]);

        expect(await screen.findByText('Edit Mail Account')).toBeInTheDocument();
        expect(screen.getByLabelText('Account Name (used in code)')).toHaveValue('support');
        expect(screen.getByLabelText('Host')).toHaveValue('smtp.example.test');
        const password = screen.getByLabelText('Password');
        expect(password).toHaveValue('');
        expect(password).toHaveAttribute('placeholder', 'Stored — leave blank to keep it');
    });

    it('slugifies the account name as it is typed', async () => {
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        fireEvent.click(await screen.findByRole('button', { name: '+ Add Account' }));
        const name = await screen.findByLabelText('Account Name (used in code)');
        fireEvent.change(name, { target: { value: 'Customer Support!' } });

        expect(name).toHaveValue('customer-support');
    });

    it('swaps the credential fields when the type changes to Azure', async () => {
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        fireEvent.click(await screen.findByRole('button', { name: '+ Add Account' }));
        expect(await screen.findByLabelText('Host')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Type'), { target: { value: 'azure' } });
        expect(screen.getByLabelText('Tenant ID')).toBeInTheDocument();
        expect(screen.getByLabelText('Client Secret')).toBeInTheDocument();
        expect(screen.queryByLabelText('Host')).not.toBeInTheDocument();
    });

    it('surfaces a field error from the server on the right field', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { 'config.host': ['The host field is required.'] },
        });
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        fireEvent.click(await screen.findByRole('button', { name: '+ Add Account' }));
        fireEvent.click(await screen.findByText('Save Account'));

        expect(await screen.findByText('The host field is required.')).toBeInTheDocument();
    });

    it('keeps Send disabled until both an account and a recipient are chosen', async () => {
        wrap();

        fireEvent.click(await screen.findByText('✉️ Email'));
        const send = await screen.findByText('Send');
        expect(send).toBeDisabled();

        fireEvent.change(screen.getByLabelText('Account'), { target: { value: '1' } });
        expect(send).toBeDisabled();
        fireEvent.change(screen.getByLabelText('Recipient'), { target: { value: 'someone@example.test' } });
        expect(send).not.toBeDisabled();
    });
});
