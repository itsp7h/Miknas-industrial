import { useEffect, useState } from 'react';
import { apiGet } from '../api/client';
import { echo } from '../echo';

export default function NotificationBell({ currentUserId }) {
    const [notifications, setNotifications] = useState([]);
    const [open, setOpen] = useState(false);

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

    return (
        <div style={{ position: 'relative' }}>
            <button aria-label="Notifications" onClick={() => setOpen((v) => !v)}>
                🔔{notifications.length > 0 && <span>{notifications.length}</span>}
            </button>
            {open && (
                <div style={{
                    position: 'absolute', right: 0, top: '100%', width: 280,
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 8, zIndex: 50,
                }}>
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
            )}
        </div>
    );
}
