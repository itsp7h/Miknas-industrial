import { Link, useParams } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import RequestSheet from '../../../components/purchase/requests/RequestSheet';
import useRequestSheet from '../../../components/purchase/requests/useRequestSheet';
import { useRequestModal } from '../../../components/purchase/requests/RequestModalProvider';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';
import { Hero, MobilePage } from '../../../components/mobile/ui';
import { MONO } from '../../../components/mobile/theme';

export default function RequestSheetPage() {
    const { id } = useParams();
    const sheet = useRequestSheet(id);
    const { request } = sheet;
    const { openEdit } = useRequestModal();

    useSetPageTitle(request ? `MPR ${request.request_number}` : null);

    return (
        <MobilePage>
            <Hero
                zone="purchase"
                back={request ? { to: `/app/purchase/pipeline/${request.id}`, label: 'Pipeline' } : { to: '/app/purchase/pipeline', label: 'Pipeline' }}
                title={request?.request_number ?? (sheet.loading ? 'Loading…' : 'Not found')}
                titleStyle={{ fontFamily: MONO, fontSize: 22, fontWeight: 500 }}
                subtitle="Material Purchase Request"
            />
            <div>

            {sheet.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!sheet.loading && !request && (
                <p style={{ fontSize: 14, color: '#64748b' }}>That purchase request could not be found.</p>
            )}

            {request && (
                <>
                    {/* Stacked full-width actions rather than a wrapped row. */}
                    <div style={{ display: 'grid', gap: 8, marginBottom: 16 }}>
                        <a
                            href={request.print_url} target="_blank" rel="noreferrer" className="btn-primary"
                            style={{ justifyContent: 'center' }}
                        >
                            Print MPR Form
                        </a>
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
        </MobilePage>
    );
}
