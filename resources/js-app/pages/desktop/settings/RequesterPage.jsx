import RequesterCard from '../../../components/settings/requester/RequesterCard';

export default function RequesterPage() {
    return (
        <div>
            <div className="mb-5">
                <h1 className="page-title">Requested By</h1>
                <p className="page-subtitle">
                    The people a purchase request can be raised for, and the companies each one requests for.
                    The MPR form offers only the chosen company&rsquo;s people.
                </p>
            </div>

            <RequesterCard />
        </div>
    );
}
