import { Link } from 'react-router-dom';
import NeedsBadge from './NeedsBadge';
import { STATUS_LABELS, badgeClassFor, formatDate, qty } from './grnStyles';

const ACCEPT = '.pdf,.jpg,.jpeg,.png';
const MAX_OTHER = 5;

function Row({ label, children }) {
    return (
        <div className="flex justify-between">
            <dt className="text-gray-500">{label}</dt>
            <dd className="text-gray-800 m-0">{children}</dd>
        </div>
    );
}

/** "1.2 MB", "340 KB" — enough to tell a scan from a one-page PDF. */
function fileSize(bytes) {
    if (!bytes) return '';
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * A small button that opens the file picker and uploads what is chosen. Shown
 * disabled, with the reason, to someone who may not add paperwork (#14).
 */
function UploadButton({ field, label, ariaLabel, multiple = false, canUpload, uploading, onUpload }) {
    const busy = uploading === field;
    const disabled = !canUpload || !!uploading;

    return (
        <label
            className="btn-secondary btn-sm"
            title={canUpload ? undefined : 'You do not have permission to add documents to goods receipts'}
            style={{ cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1, marginLeft: 8, whiteSpace: 'nowrap' }}
        >
            {busy ? 'Uploading…' : label}
            <input
                type="file" accept={ACCEPT} multiple={multiple} disabled={disabled}
                aria-label={ariaLabel}
                style={{ display: 'none' }}
                onChange={(e) => { onUpload?.(field, e.target.files); e.target.value = ''; }}
            />
        </label>
    );
}

/**
 * The LPO, GRN and tax invoice, then any Other files. Each opens in a new tab
 * from a web route (see GrnDocumentController). A missing one can be uploaded
 * here — a receipt saves without its paperwork and is completed once all
 * three are in.
 */
function Documents({ documents, others = [], canUpload, uploading, onUpload }) {
    const upload = { canUpload, uploading, onUpload };

    return (
        <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Documents</h2>
            <dl className="space-y-3 text-sm">
                {documents.map((doc) => (
                    <div key={doc.kind} className="flex justify-between" style={{ gap: '1rem' }}>
                        <dt className="text-gray-500">{doc.label}</dt>
                        <dd className="m-0" style={{ minWidth: 0, textAlign: 'right' }}>
                            {doc.url ? (
                                <a href={doc.url} target="_blank" rel="noreferrer"
                                   className="text-blue-600 hover:underline"
                                   style={{ overflowWrap: 'anywhere' }}>
                                    {doc.name}
                                </a>
                            ) : (
                                <>
                                    <span className="text-red-600">Not uploaded</span>
                                    {onUpload && <UploadButton field={`${doc.kind}_document`} label="Upload" ariaLabel={`Upload ${doc.label}`} {...upload} />}
                                </>
                            )}
                            {doc.url && doc.size ? <span className="text-gray-400"> · {fileSize(doc.size)}</span> : null}
                        </dd>
                    </div>
                ))}
                {/* The optional extras, one link each. */}
                <div className="flex justify-between" style={{ gap: '1rem' }}>
                    <dt className="text-gray-500">Other</dt>
                    <dd className="m-0" style={{ minWidth: 0, textAlign: 'right' }}>
                        {others.length === 0 && <span className="text-gray-400">None</span>}
                        {others.map((doc) => (
                            <div key={doc.id}>
                                <a href={doc.url} target="_blank" rel="noreferrer"
                                   className="text-blue-600 hover:underline"
                                   style={{ overflowWrap: 'anywhere' }}>
                                    {doc.name}
                                </a>
                                {doc.size ? <span className="text-gray-400"> · {fileSize(doc.size)}</span> : null}
                            </div>
                        ))}
                        {onUpload && others.length < MAX_OTHER && (
                            <div style={{ marginTop: others.length ? 6 : 0 }}>
                                <UploadButton field="other_documents" label="Add files" ariaLabel="Add other files" multiple {...upload} />
                            </div>
                        )}
                    </dd>
                </div>
            </dl>
        </div>
    );
}

/** The Blade GRN show page: a details card plus the received-items table. */
export default function GrnDetail({ grn, compact = false, canUpload = false, uploading = null, onUpload }) {
    if (!grn) return null;

    const items = grn.items ?? [];

    return (
        <div>
            <div className={`grid grid-cols-1 ${compact ? '' : 'lg:grid-cols-2'} gap-4 mb-6`}>
                <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">GRN Details</h2>
                    <dl className="space-y-3 text-sm">
                        <Row label="GRN Number">
                            <span className="font-mono font-semibold">{grn.grn_number}</span>
                        </Row>
                        <Row label="Purchase Order">
                            <Link to={`/app/purchase/orders/${grn.purchase_order_id}`} className="font-mono text-blue-600 hover:underline">
                                {grn.po_number}
                            </Link>
                        </Row>
                        <Row label="Supplier"><span className="font-medium">{grn.supplier_name ?? '-'}</span></Row>
                        <Row label="Warehouse">{grn.warehouse_name ?? '-'}</Row>
                        <Row label="Received Date">{formatDate(grn.received_date)}</Row>
                        <Row label="Status">
                            <span className={badgeClassFor(grn.status)}>
                                {STATUS_LABELS[grn.status] ?? grn.status}
                            </span>
                            <NeedsBadge grn={grn} style={{ marginLeft: 6 }} />
                        </Row>
                        {grn.received_by_name && <Row label="Received By">{grn.received_by_name}</Row>}
                        {grn.notes && (
                            <div>
                                <dt className="text-gray-500">Notes</dt>
                                <dd className="text-gray-700 mt-1">{grn.notes}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                {grn.documents && (
                    <Documents
                        documents={grn.documents} others={grn.other_documents ?? []}
                        canUpload={canUpload} uploading={uploading} onUpload={onUpload}
                    />
                )}
            </div>

            <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-200">
                    <h2 className="text-base font-semibold text-gray-700">Items Received</h2>
                </div>
                <table className="table-base">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th className="text-right">PO Qty</th>
                            <th className="text-right">Qty Received</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">No items recorded.</td></tr>
                        )}
                        {items.map((item) => (
                            <tr key={item.id}>
                                <td className="text-gray-800">{item.item_name ?? ''}</td>
                                {/* Read from the linked PO line; the Blade page printed a
                                    non-existent column here and always showed 0.00. */}
                                <td className="text-right text-gray-600">{qty(item.quantity_ordered)}</td>
                                <td className="text-right font-medium text-gray-800">{qty(item.quantity_received)}</td>
                                <td className="text-gray-600">
                                    {item.type === 'consumable'
                                        ? `Consumable${item.project_name ? ` — ${item.project_name}` : ''}`
                                        : 'Inventory'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
