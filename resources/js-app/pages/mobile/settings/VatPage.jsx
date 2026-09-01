import VatCard from '../../../components/settings/vat/VatCard';

export default function VatPage() {
    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">VAT Settings</h1>
                <p className="page-subtitle">Set the global VAT rate applied to vatable items on supplier quotes.</p>
            </div>

            {/* Full width on a phone rather than Blade's 480px column. */}
            <VatCard maxWidth="100%" />
        </div>
    );
}
