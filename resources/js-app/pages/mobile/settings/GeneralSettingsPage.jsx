/** The Settings tab on a phone. Empty until we know what belongs on it. */
export default function GeneralSettingsPage() {
    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Settings</h1>
                <p className="page-subtitle">General configuration.</p>
            </div>

            <div style={{
                background: '#fff', border: '1px dashed #cbd5e1', borderRadius: 12,
                padding: 24, textAlign: 'center',
            }}>
                <p style={{ fontSize: 14, fontWeight: 600, color: '#475569', margin: 0 }}>
                    Nothing here yet
                </p>
                <p style={{ fontSize: 12.5, color: '#94a3b8', margin: '6px 0 0' }}>
                    This tab is ready for its settings.
                </p>
            </div>
        </div>
    );
}
