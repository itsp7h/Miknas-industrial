import { useEffect, useState } from 'react';
import Card from '../components/ui/Card';
import { apiGet } from '../api/client';
import { echo } from '../echo';
import { useToast } from '../components/ui/Toast';

export default function DashboardPage({ currentUserId }) {
    const [summary, setSummary] = useState(null);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/dashboard/summary').then(setSummary);
    }, []);

    useEffect(() => {
        if (!currentUserId) return;

        const channel = echo.private(`App.Models.User.${currentUserId}`);
        channel.listen('.dashboard.pinged', (event) => {
            showToast(event.message, 'info');
        });

        return () => echo.leave(`App.Models.User.${currentUserId}`);
    }, [currentUserId, showToast]);

    return (
        <Card title="Suppliers">
            <p className="text-3xl font-bold text-gray-800">
                {summary ? summary.suppliers_total : '…'}
            </p>
        </Card>
    );
}
