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

    // Matches the Blade page's @section('title', 'MPR ' . $pr->request_number).
    useSetPageTitle(request ? `MPR ${request.request_number}` : null);

    return (
        <div>
            {sheet.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!sheet.loading && !request && (
                <p style={{ fontSize: 14, color: '#64748b' }}>That purchase request could not be found.</p>
            )}

            {request && (
                <>
                    <div style={{
                        marginBottom: 24, display: 'flex', alignItems: 'flex-start',
                        justifyContent: 'space-between', flexWrap: 'wrap', gap: 12,
                    }}>
                        <div>
                            <h1 className="page-title">{request.request_number}</h1>
                            <p className="page-subtitle">
                                <Link to="/app/purchase/pipeline" className="text-blue-600 hover:underline">
                                    Purchase Requests
                                </Link>
                                {' / '}{request.request_number}
                            </p>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <Link
                                to={`/app/purchase/pipeline/${request.id}`}
                                style={{
                                    fontSize: 14, color: '#475569', textDecoration: 'none',
                                    border: '1px solid #e2e8f0', borderRadius: 8, padding: '8px 12px',
                                    display: 'inline-flex', alignItems: 'center', gap: 4,
                                }}
                            >
                                <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                Back to Pipeline
                            </Link>
                            {/* The MPR document is DomPDF-backed, so this stays a
                                real navigation into a new tab. */}
                            <a href={request.print_url} target="_blank" rel="noreferrer" className="btn-primary">
                                Print MPR Form
                            </a>
                            {request.permissions.update && (
                                <button
                                    type="button" className="btn-secondary"
                                    onClick={() => openEdit(request.id, sheet.reload)}
                                >
                                    Edit
                                </button>
                            )}
                            {request.permissions.delete && (
                                <button type="button" className="btn-danger" onClick={() => sheet.setDeleting(true)}>
                                    Delete
                                </button>
                            )}
                        </div>
                    </div>

                    <RequestSheet request={request} />

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
