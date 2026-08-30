import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import useViewport from './hooks/useViewport';
import DashboardPage from './pages/DashboardPage';
import DesktopSupplierListPage from './pages/desktop/purchase/SupplierListPage';
import MobileSupplierListPage from './pages/mobile/purchase/SupplierListPage';
import DesktopPipelineBoardPage from './pages/desktop/purchase/PipelineBoardPage';
import MobilePipelineBoardPage from './pages/mobile/purchase/PipelineBoardPage';
import DesktopItemListPage from './pages/desktop/inventory/ItemListPage';
import MobileItemListPage from './pages/mobile/inventory/ItemListPage';
import DesktopWarehouseListPage from './pages/desktop/inventory/WarehouseListPage';
import MobileWarehouseListPage from './pages/mobile/inventory/WarehouseListPage';
import DesktopStockMovementPage from './pages/desktop/inventory/StockMovementPage';
import MobileStockMovementPage from './pages/mobile/inventory/StockMovementPage';
import DesktopStockSummaryPage from './pages/desktop/inventory/reports/StockSummaryPage';
import MobileStockSummaryPage from './pages/mobile/inventory/reports/StockSummaryPage';
import DesktopMovementReportPage from './pages/desktop/inventory/reports/MovementReportPage';
import MobileMovementReportPage from './pages/mobile/inventory/reports/MovementReportPage';
import DesktopLowStockPage from './pages/desktop/inventory/reports/LowStockPage';
import MobileLowStockPage from './pages/mobile/inventory/reports/LowStockPage';
import DesktopValuationPage from './pages/desktop/inventory/reports/ValuationPage';
import MobileValuationPage from './pages/mobile/inventory/reports/ValuationPage';

export default function App({
    currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken,
    canViewAllPurchaseRequests, canViewActivePipeline, canViewOwnPurchaseRequests,
}) {
    const viewport = useViewport();
    const SupplierListPage = viewport === 'mobile' ? MobileSupplierListPage : DesktopSupplierListPage;
    const PipelineBoardPage = viewport === 'mobile' ? MobilePipelineBoardPage : DesktopPipelineBoardPage;
    const ItemListPage = viewport === 'mobile' ? MobileItemListPage : DesktopItemListPage;
    const WarehouseListPage = viewport === 'mobile' ? MobileWarehouseListPage : DesktopWarehouseListPage;
    const StockMovementPage = viewport === 'mobile' ? MobileStockMovementPage : DesktopStockMovementPage;
    const StockSummaryPage = viewport === 'mobile' ? MobileStockSummaryPage : DesktopStockSummaryPage;
    const MovementReportPage = viewport === 'mobile' ? MobileMovementReportPage : DesktopMovementReportPage;
    const LowStockPage = viewport === 'mobile' ? MobileLowStockPage : DesktopLowStockPage;
    const ValuationPage = viewport === 'mobile' ? MobileValuationPage : DesktopValuationPage;

    return (
        <AppShell
            currentUserId={currentUserId}
            userName={userName}
            userEmail={userEmail}
            isAdmin={isAdmin}
            logoutUrl={logoutUrl}
            csrfToken={csrfToken}
        >
            <Routes>
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} />} />
                <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                <Route path="/app/purchase/pipeline" element={(
                    <PipelineBoardPage
                        currentUserId={currentUserId}
                        canViewAllPurchaseRequests={canViewAllPurchaseRequests}
                        canViewActivePipeline={canViewActivePipeline}
                        canViewOwnPurchaseRequests={canViewOwnPurchaseRequests}
                    />
                )} />
                <Route path="/app/inventory/items" element={<ItemListPage />} />
                <Route path="/app/inventory/warehouses" element={<WarehouseListPage />} />
                <Route path="/app/inventory/movements" element={<StockMovementPage />} />
                <Route path="/app/inventory/reports/summary" element={<StockSummaryPage />} />
                <Route path="/app/inventory/reports/movement" element={<MovementReportPage />} />
                <Route path="/app/inventory/reports/low-stock" element={<LowStockPage />} />
                <Route path="/app/inventory/reports/valuation" element={<ValuationPage />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
