/**
 * The Settings tab, scaffolded and deliberately empty.
 *
 * It exists so the route, the menu entry and the `settings.*` permissions are
 * in place; what belongs on it is still to be decided. Fill the panel below and
 * delete the placeholder.
 */
export default function GeneralSettingsPage() {
    return (
        <div>
            <div className="mb-5">
                <h1 className="page-title">Settings</h1>
                <p className="page-subtitle">General configuration for the system.</p>
            </div>

            <div style={{
                background: '#fff', border: '1px dashed #cbd5e1', borderRadius: 14,
                padding: 32, textAlign: 'center',
            }}>
                <p style={{ fontSize: 14, fontWeight: 600, color: '#475569', margin: 0 }}>
                    Nothing here yet
                </p>
                <p style={{ fontSize: 13, color: '#94a3b8', margin: '6px 0 0' }}>
                    This tab is ready for its settings.
                </p>
            </div>
        </div>
    );
}
