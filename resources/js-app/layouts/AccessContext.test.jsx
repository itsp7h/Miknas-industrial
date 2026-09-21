import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AccessProvider, useAccess } from './AccessContext';
import { ToastProvider } from '../components/ui/Toast';
import GrnListPage from '../pages/desktop/purchase/GrnListPage';
import * as client from '../api/client';

vi.mock('../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

function Probe() {
    const { can, isAdmin } = useAccess();

    return (
        <ul>
            <li>create: {String(can('goods-receipts.create'))}</li>
            <li>view: {String(can('goods-receipts.view'))}</li>
            <li>admin: {String(isAdmin)}</li>
        </ul>
    );
}

describe('AccessContext', () => {
    it('answers from the permission list', () => {
        render(
            <AccessProvider isAdmin={false} permissions={['goods-receipts.view']}>
                <Probe />
            </AccessProvider>
        );

        expect(screen.getByText('view: true')).toBeInTheDocument();
        expect(screen.getByText('create: false')).toBeInTheDocument();
    });

    /** Mirrors the Gate::before in AppServiceProvider, which grants Admin everything. */
    it('says yes to an Admin whatever the list holds', () => {
        render(
            <AccessProvider isAdmin permissions={[]}>
                <Probe />
            </AccessProvider>
        );

        expect(screen.getByText('create: true')).toBeInTheDocument();
    });

    it('denies everything outside a provider rather than assuming the best', () => {
        render(<Probe />);

        expect(screen.getByText('create: false')).toBeInTheDocument();
        expect(screen.getByText('admin: false')).toBeInTheDocument();
    });
});

describe('a list page offering an action it cannot complete', () => {
    const renderGrnPage = (permissions) => render(
        <MemoryRouter>
            <ToastProvider>
                <AccessProvider isAdmin={false} permissions={permissions}>
                    <GrnListPage />
                </AccessProvider>
            </ToastProvider>
        </MemoryRouter>
    );

    it('hides + New GRN from someone who may only view them', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        renderGrnPage(['goods-receipts.view']);

        // Offering it produced a modal whose first fetch was refused, which
        // read as a broken screen rather than a closed door.
        expect(await screen.findByText('Goods Receipt Notes')).toBeInTheDocument();
        expect(screen.queryByText('+ New GRN')).not.toBeInTheDocument();
    });

    it('offers it to someone who may create them', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        renderGrnPage(['goods-receipts.view', 'goods-receipts.create']);

        expect(await screen.findByText('+ New GRN')).toBeInTheDocument();
    });
});
