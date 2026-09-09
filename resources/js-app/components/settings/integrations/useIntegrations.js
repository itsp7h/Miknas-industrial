import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '../../../api/client';
import { useToast } from '../../ui/Toast';

const EMPTY_WHATSAPP = {
    enabled: false, instance_id: '', webhook_path: 'ultra-message/webhook',
    token_set: false, webhook_secret_set: false, base_url: '',
};

/** WhatsApp settings and the mail accounts, with their test actions. */
export default function useIntegrations() {
    const [tab, setTab] = useState('whatsapp');
    const [whatsapp, setWhatsapp] = useState(EMPTY_WHATSAPP);
    const [accounts, setAccounts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [accountModal, setAccountModal] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    const loadWhatsapp = useCallback(() => apiGet('/settings/integrations/whatsapp')
        .then((response) => setWhatsapp({ ...EMPTY_WHATSAPP, ...response }))
        .catch(() => showToast('Failed to load the WhatsApp settings.', 'error')),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []);

    const loadAccounts = useCallback(() => apiGet('/settings/mail-accounts')
        .then((response) => setAccounts(response.data ?? []))
        .catch(() => showToast('Failed to load the mail accounts.', 'error')),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []);

    useEffect(() => {
        Promise.all([loadWhatsapp(), loadAccounts()]).finally(() => setLoading(false));
    }, [loadWhatsapp, loadAccounts]);

    async function saveWhatsapp(values) {
        const response = await apiPut('/settings/integrations/whatsapp', values);
        setWhatsapp({ ...EMPTY_WHATSAPP, ...response });
        showToast('WhatsApp settings saved.', 'success');
    }

    /** Both test endpoints answer 200 with success:false, so read the body. */
    async function testWhatsapp() {
        const result = await apiPost('/settings/integrations/whatsapp/test');

        return result.success
            ? { ok: true, message: `Connected — ${result.status?.accountStatus ?? 'instance reachable'}` }
            : { ok: false, message: result.message || 'Connection failed.' };
    }

    async function sendWhatsappTest(payload) {
        const result = await apiPost('/settings/integrations/whatsapp/test-message', payload);

        return result.success
            ? { ok: true, message: 'Message sent.' }
            : { ok: false, message: result.message || 'Send failed.' };
    }

    async function saveAccount(account, values) {
        if (account) await apiPut(`/settings/mail-accounts/${account.id}`, values);
        else await apiPost('/settings/mail-accounts', values);

        await loadAccounts();
        showToast(account ? 'Mail account updated.' : 'Mail account added.', 'success');
    }

    async function toggleAccount(account) {
        try {
            const response = await apiPatch(`/settings/mail-accounts/${account.id}/toggle`);
            setAccounts((prev) => prev.map((row) => (row.id === account.id ? response.data : row)));
        } catch (err) {
            showToast(err.message || 'Failed to change that account.', 'error');
        }
    }

    async function handleDelete() {
        const account = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/settings/mail-accounts/${account.id}`);
            setAccounts((prev) => prev.filter((row) => row.id !== account.id));
            showToast(`Mail account "${account.label}" deleted.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that account.', 'error');
        }
    }

    async function testAccount(account) {
        const result = await apiPost(`/settings/mail-accounts/${account.id}/test`);

        return result.success
            ? { ok: true, message: 'Connection OK.' }
            : { ok: false, message: result.message || 'Connection failed.' };
    }

    async function sendTestEmail(accountId, to) {
        const result = await apiPost(`/settings/mail-accounts/${accountId}/send-test`, { to });

        return result.success
            ? { ok: true, message: 'Email sent.' }
            : { ok: false, message: result.message || 'Send failed.' };
    }

    return {
        tab, setTab, loading,
        whatsapp, saveWhatsapp, testWhatsapp, sendWhatsappTest,
        accounts, accountModal, setAccountModal, saveAccount, toggleAccount, testAccount,
        deleting, setDeleting, handleDelete, sendTestEmail,
    };
}
