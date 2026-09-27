import RequesterCard from '../../../components/settings/requester/RequesterCard';

export default function RequesterPage() {
    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Requested By</h1>
                <p className="page-subtitle">Who a purchase request can be raised for, per company.</p>
            </div>

            <RequesterCard compact />
        </div>
    );
}
