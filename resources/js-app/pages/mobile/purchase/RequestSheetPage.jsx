import { Link, useParams } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import RequestSheet from '../../../components/purchase/requests/RequestSheet';
import useRequestSheet from '../../../components/purchase/requests/useRequestSheet';
import { useRequestModal } from '../../../components/purchase/requests/RequestModalProvider';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function RequestSheetPage() {
    const { id } = useParams();
    const sheet = useRequestSheet(id);
    const { request } = sheet;
    const { openEdit } = useRequestModal();

    useSetPageTitle(request ? `MPR ${request.request_number}` : null);

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <Link to="/app/purchase/pipeline" style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none' }}>
                    ← Purchase Requests
                </Link>
            </div>

            {sheet.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!sheet.loading && !request && (
                <p style={{ fontSize: 14, color: '#64748b' }}>That purchase request could not be found.</p>
            )}

            {request && (
                <>
                    <div style={{ marginBottom: 14 }}>
                        <h1 className="page-title">{request.request_number}</h1>
                        <p className="page-subtitle">Material Purchase Request</p>
                    </div>

                    {/* Stacked full-width actions rather than a wrapped row. */}
                    <div style={{ display: 'grid', gap: 8, marginBottom: 16 }}>
                        <a
                            href={request.print_url} target="_blank" rel="noreferrer" className="btn-primary"
                            style={{ justifyContent: 'center' }}
                        >
                            Print MPR Form
                        </a>
                        <Link
                            to={`/app/purchase/pipeline/${request.id}`} className="btn-secondary"
                            style={{ justifyContent: 'center', textDecoration: 'none' }}
                        >
                            Back to Pipeline
                        </Link>
                        {request.permissions.update && (
                            <button
                                type="button" className="btn-secondary" style={{ justifyContent: 'center' }}
                                onClick={() => openEdit(request.id, sheet.reload)}
                            >
                                Edit
                            </button>
                        )}
                        {request.permissions.delete && (
                            <button
                                type="button" className="btn-danger" style={{ justifyContent: 'center' }}
                                onClick={() => sheet.setDeleting(true)}
                            >
                                Delete
                            </button>
                        )}
                    </div>

                    <RequestSheet request={request} compact />

                    <ConfirmModal
                        open={sheet.deleting}
                        title={`Delete ${request.request_number}?`}
                        body="The request and all of its item lines are removed. This cannot be undone."
                        onConfirm={sheet.destroy}
                        onCancel={() => sheet.setDeleting(false)}
                    />
                </>
            )}
        </div>
    );
}
