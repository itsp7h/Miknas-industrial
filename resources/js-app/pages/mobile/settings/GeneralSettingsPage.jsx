import LpoNumberingCard from '../../../components/settings/lpoNumbering/LpoNumberingCard';

export default function GeneralSettingsPage() {
    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Settings</h1>
                <p className="page-subtitle">Document numbering and system configuration.</p>
            </div>

            <LpoNumberingCard maxWidth="100%" />
        </div>
    );
}
