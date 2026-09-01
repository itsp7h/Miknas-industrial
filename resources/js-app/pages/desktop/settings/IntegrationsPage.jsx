import ConfirmModal from '../../../components/ui/ConfirmModal';
import EmailPanel from '../../../components/settings/integrations/EmailPanel';
import MailAccountModal from '../../../components/settings/integrations/MailAccountModal';
import TabPills from '../../../components/settings/integrations/TabPills';
import WhatsAppPanel from '../../../components/settings/integrations/WhatsAppPanel';
import useIntegrations from '../../../components/settings/integrations/useIntegrations';

export default function IntegrationsPage({ compact = false }) {
    const i = useIntegrations();

    return (
        <div>
            <div className="mb-6">
                <h1 className="page-title">Settings — Integrations</h1>
                <p className="page-subtitle">Configure third-party service integrations.</p>
            </div>

            <TabPills tab={i.tab} onChange={i.setTab} />

            {i.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!i.loading && i.tab === 'whatsapp' && (
                <WhatsAppPanel
                    whatsapp={i.whatsapp}
                    onSave={i.saveWhatsapp}
                    onTest={i.testWhatsapp}
                    onSendTest={i.sendWhatsappTest}
                    compact={compact}
                />
            )}

            {!i.loading && i.tab === 'email' && (
                <EmailPanel
                    accounts={i.accounts}
                    onAdd={() => i.setAccountModal({ account: null })}
                    onEdit={(account) => i.setAccountModal({ account })}
                    onToggle={i.toggleAccount}
                    onDelete={i.setDeleting}
                    onSendTest={i.sendTestEmail}
                    compact={compact}
                />
            )}

            <MailAccountModal
                open={!!i.accountModal}
                account={i.accountModal?.account}
                onClose={() => i.setAccountModal(null)}
                onSave={i.saveAccount}
                onTest={i.testAccount}
            />
            <ConfirmModal
                open={!!i.deleting}
                title="Delete this mail account?"
                body={i.deleting
                    ? `"${i.deleting.label}" will be permanently removed. Anything sending through Mail::mailer('${i.deleting.name}') will stop working.`
                    : ''}
                onConfirm={i.handleDelete}
                onCancel={() => i.setDeleting(null)}
            />
        </div>
    );
}
