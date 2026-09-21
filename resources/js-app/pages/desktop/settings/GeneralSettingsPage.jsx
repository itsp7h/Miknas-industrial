import DocumentNumberingCard from '../../../components/settings/documentNumbering/DocumentNumberingCard';

export default function GeneralSettingsPage() {
    return (
        <div>
            <div className="mb-5">
                <h1 className="page-title">Settings</h1>
                <p className="page-subtitle">How documents are numbered, and other system-wide configuration.</p>
            </div>

            <DocumentNumberingCard />
        </div>
    );
}
