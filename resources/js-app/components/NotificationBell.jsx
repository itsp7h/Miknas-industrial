import { useEffect, useState } from 'react';
import { apiGet, apiPost } from '../api/client';
import { echo } from '../echo';

/**
 * Matches the Blade topbar's bell: a 34px bordered button with the same stroke
 * icon and red count badge, and a 320px dropdown headed "Notifications" with a
 * "Mark all read" control.
 */
export default function NotificationBell({ currentUserId }) {
    const [notifications, setNotifications] = useState([]);
    const [open, setOpen] = useState(false);
    const [hover, setHover] = useState(false);

    useEffect(() => {
        apiGet('/notifications/unread')
            .then((res) => setNotifications(res.notifications ?? []))
            .catch(() => {});
    }, []);

    useEffect(() => {
        if (!currentUserId) return;

        const channel = echo.private(`App.Models.User.${currentUserId}`);
        channel.listen('.notification.pushed', (event) => {
            setNotifications((prev) => [event, ...prev]);
        });

        return () => echo.leave(`App.Models.User.${currentUserId}`);
    }, [currentUserId]);

    async function markAllRead() {
        // Clear locally first so the badge responds immediately; the request is
        // idempotent, so a failure just leaves the server to be re-read later.
        setNotifications([]);
        try {
            await apiPost('/notifications/read-all');
        } catch {
            /* nothing actionable for the user here */
        }
    }

    return (
        <div style={{ position: 'relative' }}>
            <button
                aria-label="Notifications"
                title="Notifications"
                onClick={() => setOpen((v) => !v)}
                onMouseEnter={() => setHover(true)}
                onMouseLeave={() => setHover(false)}
                style={{
                    position: 'relative', width: 34, height: 34, borderRadius: 9,
                    border: '1px solid #e2e8f0', background: hover ? '#f1f5f9' : '#fff',
                    cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center',
                    color: '#64748b', transition: 'background .15s, color .15s', flexShrink: 0,
                }}
            >
                <svg width="17" height="17" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                {notifications.length > 0 && (
                    <span style={{
                        position: 'absolute', top: -4, right: -4, background: '#ef4444', color: '#fff',
                        fontSize: 10, fontWeight: 700, minWidth: 17, height: 17, borderRadius: 9,
                        padding: '0 4px', display: 'flex', alignItems: 'center', justifyContent: 'center',
                        border: '2px solid #fff',
                    }}>
                        {notifications.length}
                    </span>
                )}
            </button>

            {open && (
                <div style={{
                    position: 'absolute', top: 42, right: 0, background: '#fff',
                    border: '1.5px solid #e2e8f0', borderRadius: 14,
                    boxShadow: '0 12px 32px rgba(0,0,0,.12)', width: 320, zIndex: 9000, overflow: 'hidden',
                }}>
                    <div style={{
                        padding: '12px 16px', borderBottom: '1px solid #f1f5f9',
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    }}>
                        <span style={{ fontSize: 13, fontWeight: 700, color: '#0f172a' }}>Notifications</span>
                        <button
                            onClick={markAllRead}
                            style={{
                                fontSize: 11, color: '#2563eb', background: 'none', border: 'none',
                                cursor: 'pointer', fontWeight: 600,
                            }}
                        >
                            Mark all read
                        </button>
                    </div>
                    <div style={{ maxHeight: 320, overflowY: 'auto' }}>
                        {notifications.length === 0 ? (
                            <div style={{ padding: 24, textAlign: 'center', color: '#94a3b8', fontSize: 13 }}>
                                No new notifications
                            </div>
                        ) : (
                            notifications.map((n) => (
                                <div key={n.id} style={{ padding: 12, borderBottom: '1px solid #f1f5f9' }}>
                                    <div style={{ fontWeight: 600, fontSize: 13 }}>{n.title}</div>
                                    <div style={{ fontSize: 12, color: '#64748b' }}>{n.body}</div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
