import DesktopIntegrationsPage from '../../desktop/settings/IntegrationsPage';

/**
 * The two panels are already a card with a form and a test box; on a phone they
 * simply stack instead of sitting side by side, which `compact` does. Everything
 * else — the pills, the cards, the modal — is identical, so this is the one page
 * where a shared tree with a layout flag beats two copies that would drift.
 */
export default function IntegrationsPage() {
    return <DesktopIntegrationsPage compact />;
}
