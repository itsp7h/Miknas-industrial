import VatCard from '../../../components/settings/vat/VatCard';

export default function VatPage() {
    return (
        <div>
            <div className="mb-5">
                <h1 className="page-title">VAT Settings</h1>
                <p className="page-subtitle">Set the global VAT rate applied to vatable items on supplier quotes.</p>
            </div>

            <VatCard />
        </div>
    );
}
