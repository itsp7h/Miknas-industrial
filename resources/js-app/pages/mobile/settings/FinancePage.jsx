import FinanceCards from '../../../components/settings/finance/FinanceCards';

export default function FinancePage() {
    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Finance</h1>
                <p className="page-subtitle">VAT rate and display currency.</p>
            </div>

            <FinanceCards maxWidth="100%" />
        </div>
    );
}
