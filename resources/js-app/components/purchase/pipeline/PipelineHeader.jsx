import { formatDate } from './pipelineStyles';

/**
 * The header card: request number, stage badge, emoji meta row, the two
 * top-right actions, and the progress bar — amber while in flight, green once
 * complete, exactly as the Blade page rendered it.
 */
export default function PipelineHeader({ request, compact = false }) {
    const done = request.is_done;
    const meta = [
        request.project_name && `📁 ${request.project_name}`,
        request.department && `🏢 ${request.department}`,
        request.requested_by_name && `👤 ${request.requested_by_name}`,
        request.date && `📅 ${formatDate(request.date)}`,
    ].filter(Boolean);

    return (
        <div style={{
            background: '#fff', borderRadius: 16, boxShadow: '0 2px 12px rgba(0,0,0,.06)',
            overflow: 'hidden', marginBottom: 20,
        }}>
            <div style={{
                padding: compact ? '16px 18px' : '20px 24px', display: 'flex', alignItems: 'flex-start',
                justifyContent: 'space-between', flexWrap: 'wrap', gap: 12,
            }}>
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <h1 style={{ fontSize: compact ? 18 : 20, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                            {request.request_number}
                        </h1>
                        <span style={{
                            fontSize: 11, fontWeight: 700, padding: '4px 12px', borderRadius: 20,
                            background: done ? '#dcfce7' : '#fffbeb',
                            color: done ? '#15803d' : '#92400e',
                        }}>
                            {request.stage_labels[request.stage]}
                        </span>
                    </div>
                    <div style={{
                        fontSize: 13, color: '#64748b', marginTop: 6,
                        display: 'flex', flexWrap: 'wrap', gap: 14,
                    }}>
                        {meta.map((entry) => <span key={entry}>{entry}</span>)}
                    </div>
                </div>

                {/* Editing and the full request sheet are still Blade pages. */}
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {request.permissions.update && (
                        <a
                            href={`/purchase/requests/${request.id}/edit`}
                            style={{
                                fontSize: 12, color: '#64748b', textDecoration: 'none',
                                border: '1px solid #e2e8f0', padding: '6px 14px', borderRadius: 7,
                                whiteSpace: 'nowrap',
                            }}
                        >
                            Edit
                        </a>
                    )}
                    <a
                        href={`/purchase/requests/${request.id}`}
                        style={{
                            fontSize: 12, color: '#64748b', textDecoration: 'none',
                            border: '1px solid #e2e8f0', padding: '6px 14px', borderRadius: 7,
                            whiteSpace: 'nowrap',
                        }}
                    >
                        View Full Request →
                    </a>
                </div>
            </div>
            <div style={{ height: 4, background: '#f1f5f9' }}>
                <div style={{
                    height: 4, background: done ? '#22c55e' : '#f59e0b',
                    width: `${request.progress_pct}%`, transition: 'width .4s ease',
                }} />
            </div>
        </div>
    );
}
