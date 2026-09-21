import FinanceCards from '../../../components/settings/finance/FinanceCards';

export default function FinancePage() {
    return (
        <div>
            <div className="mb-5">
                <h1 className="page-title">Finance</h1>
                <p className="page-subtitle">The VAT rate applied to vatable items, and the currency amounts are shown in.</p>
            </div>

            <FinanceCards />
        </div>
    );
}
