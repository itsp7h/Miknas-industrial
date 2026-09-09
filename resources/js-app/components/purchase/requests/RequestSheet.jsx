const STATUS_BADGE = {
    pending: 'badge-yellow',
    approved: 'badge-green',
    rejected: 'badge-red',
    ordered: 'badge-blue',
};

/** Blade printed dates as d-m-Y on this page, not the ISO the API sends. */
export function ddmmyyyy(value) {
    if (!value) return '—';
    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}-${month}-${year}`;
}

/** Blade printed the approval stamp as "01 Sep 2026, 14:07". */
export function datetime(value) {
    if (!value) return '—';

    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
}

function Field({ label, value, span }) {
    return (
        <div style={span ? { gridColumn: `span ${span}` } : undefined}>
            <p style={{ color: '#6b7280', fontSize: 13 }}>{label}</p>
            <p style={{ fontWeight: 600, color: '#1f2937', fontSize: 13 }}>{value || '—'}</p>
        </div>
    );
}

function SectionHeading({ children }) {
    return (
        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide" style={{ marginBottom: 16 }}>
            {children}
        </h2>
    );
}

/**
 * The read-only MPR sheet, as the Blade page laid it out: a status badge, the
 * project/department grid, the material table, and the approval card only once
 * the request has been approved.
 */
export default function RequestSheet({ request, compact = false }) {
    // Blade's `grid-cols-2 sm:grid-cols-3`.
    const columns = compact ? 2 : 3;

    return (
        <>
            <div style={{ marginBottom: 16, fontSize: 14 }}>
                <span style={{ fontWeight: 500 }}>Status:</span>
                <span className={STATUS_BADGE[request.status] ?? 'badge-gray'} style={{ marginLeft: 4 }}>
                    {request.status ? request.status[0].toUpperCase() + request.status.slice(1) : '—'}
                </span>
            </div>

            <div className="card card-body" style={{ marginBottom: 24 }}>
                <SectionHeading>Project / Department Details</SectionHeading>
                <div style={{ display: 'grid', gridTemplateColumns: `repeat(${columns},minmax(0,1fr))`, gap: 16 }}>
                    <Field label="MPR Number" value={request.request_number} />
                    <Field label="Date" value={ddmmyyyy(request.date)} />
                    <Field label="Project / Site Name" value={request.project_name} />
                    <Field label="Requested By" value={request.requested_by_name} />
                    <Field label="Required Date" value={request.required_date_text} />
                    <Field label="Location / Site" value={request.location} />
                    {request.department && <Field label="Department" value={request.department} />}
                    {request.remarks && <Field label="Remarks" value={request.remarks} span={columns} />}
                </div>
            </div>

            <div className="card card-body" style={{ marginBottom: 24 }}>
                <SectionHeading>Material Details</SectionHeading>
                <div className="table-wrapper" style={{ overflowX: 'auto' }}>
                    <table className="table-base" style={{ minWidth: compact ? 620 : undefined }}>
                        <thead>
                            <tr>
                                <th style={{ width: 48 }}>S.No</th>
                                <th>Description of Material</th>
                                <th>Unit</th>
                                <th>Qty Required</th>
                                <th>Purpose / Use</th>
                                <th>Required Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {request.items.map((item, index) => (
                                <tr key={item.id}>
                                    <td style={{ textAlign: 'center' }}>{index + 1}</td>
                                    <td style={{ fontWeight: 500 }}>{item.description}</td>
                                    <td>{item.unit || '—'}</td>
                                    <td>{Number(item.quantity_required).toFixed(2)}</td>
                                    <td>{item.purpose_use || '—'}</td>
                                    <td>{ddmmyyyy(item.required_date)}</td>
                                </tr>
                            ))}
                            {request.items.length === 0 && (
                                <tr><td colSpan={6} style={{ color: '#94a3b8' }}>No items on this request.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Only one of these can show: both are keyed off the status, so a
                request approved after a refusal shows the approval and keeps
                the refusal as history. */}
            {request.rejection && (
                <div className="card card-body" style={{ marginBottom: 24, borderColor: '#fecaca' }}>
                    <SectionHeading>Rejection Info</SectionHeading>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2,minmax(0,1fr))', gap: 16 }}>
                        <Field label="Rejected By" value={request.rejection.rejected_by_name} />
                        <Field
                            label="Rejected At"
                            value={request.rejection.rejected_at ? datetime(request.rejection.rejected_at) : '—'}
                        />
                        <div style={{ gridColumn: 'span 2' }}>
                            <p style={{ color: '#6b7280', fontSize: 13 }}>Reason</p>
                            <p style={{
                                fontWeight: 500, color: '#7f1d1d', fontSize: 13, background: '#fef2f2',
                                border: '1px solid #fecaca', borderRadius: 8, padding: '8px 10px', margin: '4px 0 0',
                            }}>
                                {request.rejection.reason || '—'}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {request.approval && (
                <div className="card card-body">
                    <SectionHeading>Approval Info</SectionHeading>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2,minmax(0,1fr))', gap: 16 }}>
                        <Field label="Approved By" value={request.approval.approved_by_name} />
                        <Field label="Approved At" value={datetime(request.approval.approved_at)} />
                    </div>
                </div>
            )}
        </>
    );
}
