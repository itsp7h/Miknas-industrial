import CompanyWarehouseCard from '../../../components/settings/companyWarehouse/CompanyWarehouseCard';
import DocumentNumberingCard from '../../../components/settings/documentNumbering/DocumentNumberingCard';

export default function GeneralSettingsPage() {
    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Settings</h1>
                <p className="page-subtitle">Document numbering and system configuration.</p>
            </div>

            <DocumentNumberingCard maxWidth="100%" />

            <div style={{ marginTop: 14 }}>
                <CompanyWarehouseCard maxWidth="100%" />
            </div>
        </div>
    );
}
