import { Routes, Route } from 'react-router-dom';
import AppShell from './layouts/AppShell';
import useViewport from './hooks/useViewport';
import DesktopDashboardPage from './pages/desktop/DashboardPage';
import MobileDashboardPage from './pages/mobile/DashboardPage';
import DesktopSupplierListPage from './pages/desktop/purchase/SupplierListPage';
import MobileSupplierListPage from './pages/mobile/purchase/SupplierListPage';
import DesktopPurchaseOrderListPage from './pages/desktop/purchase/PurchaseOrderListPage';
import MobilePurchaseOrderListPage from './pages/mobile/purchase/PurchaseOrderListPage';
import DesktopPurchaseOrderDetailPage from './pages/desktop/purchase/PurchaseOrderDetailPage';
import MobilePurchaseOrderDetailPage from './pages/mobile/purchase/PurchaseOrderDetailPage';
import DesktopSupplierPaymentListPage from './pages/desktop/purchase/SupplierPaymentListPage';
import MobileSupplierPaymentListPage from './pages/mobile/purchase/SupplierPaymentListPage';
import DesktopSupplierInvoiceListPage from './pages/desktop/purchase/SupplierInvoiceListPage';
import MobileSupplierInvoiceListPage from './pages/mobile/purchase/SupplierInvoiceListPage';
import DesktopGrnListPage from './pages/desktop/purchase/GrnListPage';
import MobileGrnListPage from './pages/mobile/purchase/GrnListPage';
import DesktopGrnDetailPage from './pages/desktop/purchase/GrnDetailPage';
import MobileGrnDetailPage from './pages/mobile/purchase/GrnDetailPage';
import DesktopPipelinePage from './pages/desktop/purchase/PipelinePage';
import MobilePipelinePage from './pages/mobile/purchase/PipelinePage';
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
import DesktopCustomerListPage from './pages/desktop/sales/CustomerListPage';
import MobileCustomerListPage from './pages/mobile/sales/CustomerListPage';
import DesktopSalesOrderListPage from './pages/desktop/sales/SalesOrderListPage';
import MobileSalesOrderListPage from './pages/mobile/sales/SalesOrderListPage';
import DesktopSalesOrderDetailPage from './pages/desktop/sales/SalesOrderDetailPage';
import MobileSalesOrderDetailPage from './pages/mobile/sales/SalesOrderDetailPage';
import DesktopDeliveryNoteListPage from './pages/desktop/sales/DeliveryNoteListPage';
import MobileDeliveryNoteListPage from './pages/mobile/sales/DeliveryNoteListPage';
import DesktopInvoiceListPage from './pages/desktop/sales/InvoiceListPage';
import MobileInvoiceListPage from './pages/mobile/sales/InvoiceListPage';
import DesktopPaymentListPage from './pages/desktop/sales/PaymentListPage';
import MobilePaymentListPage from './pages/mobile/sales/PaymentListPage';
import DesktopProductionOrderListPage from './pages/desktop/production/ProductionOrderListPage';
import MobileProductionOrderListPage from './pages/mobile/production/ProductionOrderListPage';
import DesktopProductionOrderDetailPage from './pages/desktop/production/ProductionOrderDetailPage';
import MobileProductionOrderDetailPage from './pages/mobile/production/ProductionOrderDetailPage';
import DesktopBomListPage from './pages/desktop/production/BomListPage';
import MobileBomListPage from './pages/mobile/production/BomListPage';
import DesktopMaterialIssueListPage from './pages/desktop/production/MaterialIssueListPage';
import MobileMaterialIssueListPage from './pages/mobile/production/MaterialIssueListPage';
import DesktopCompanyListPage from './pages/desktop/settings/CompanyListPage';
import MobileCompanyListPage from './pages/mobile/settings/CompanyListPage';
import DesktopProjectSettingsPage from './pages/desktop/settings/ProjectListPage';
import MobileProjectSettingsPage from './pages/mobile/settings/ProjectListPage';
import DesktopUserListPage from './pages/desktop/settings/UserListPage';
import MobileUserListPage from './pages/mobile/settings/UserListPage';
import DesktopIntegrationsPage from './pages/desktop/settings/IntegrationsPage';
import MobileIntegrationsPage from './pages/mobile/settings/IntegrationsPage';
import DesktopVatPage from './pages/desktop/settings/VatPage';
import MobileVatPage from './pages/mobile/settings/VatPage';
import DesktopProductionOutputListPage from './pages/desktop/production/ProductionOutputListPage';
import MobileProductionOutputListPage from './pages/mobile/production/ProductionOutputListPage';

export default function App({
    currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken,
    canViewAllPurchaseRequests, canViewActivePipeline, canViewOwnPurchaseRequests,
}) {
    const viewport = useViewport();
    const DashboardPage = viewport === 'mobile' ? MobileDashboardPage : DesktopDashboardPage;
    const SupplierListPage = viewport === 'mobile' ? MobileSupplierListPage : DesktopSupplierListPage;
    const PipelineBoardPage = viewport === 'mobile' ? MobilePipelineBoardPage : DesktopPipelineBoardPage;
    const PipelinePage = viewport === 'mobile' ? MobilePipelinePage : DesktopPipelinePage;
    const PurchaseOrderListPage = viewport === 'mobile' ? MobilePurchaseOrderListPage : DesktopPurchaseOrderListPage;
    const GrnListPage = viewport === 'mobile' ? MobileGrnListPage : DesktopGrnListPage;
    const SupplierInvoiceListPage = viewport === 'mobile' ? MobileSupplierInvoiceListPage : DesktopSupplierInvoiceListPage;
    const SupplierPaymentListPage = viewport === 'mobile' ? MobileSupplierPaymentListPage : DesktopSupplierPaymentListPage;
    const GrnDetailPage = viewport === 'mobile' ? MobileGrnDetailPage : DesktopGrnDetailPage;
    const PurchaseOrderDetailPage = viewport === 'mobile' ? MobilePurchaseOrderDetailPage : DesktopPurchaseOrderDetailPage;
    const ItemListPage = viewport === 'mobile' ? MobileItemListPage : DesktopItemListPage;
    const WarehouseListPage = viewport === 'mobile' ? MobileWarehouseListPage : DesktopWarehouseListPage;
    const StockMovementPage = viewport === 'mobile' ? MobileStockMovementPage : DesktopStockMovementPage;
    const StockSummaryPage = viewport === 'mobile' ? MobileStockSummaryPage : DesktopStockSummaryPage;
    const MovementReportPage = viewport === 'mobile' ? MobileMovementReportPage : DesktopMovementReportPage;
    const LowStockPage = viewport === 'mobile' ? MobileLowStockPage : DesktopLowStockPage;
    const ValuationPage = viewport === 'mobile' ? MobileValuationPage : DesktopValuationPage;
    const CustomerListPage = viewport === 'mobile' ? MobileCustomerListPage : DesktopCustomerListPage;
    const SalesOrderListPage = viewport === 'mobile' ? MobileSalesOrderListPage : DesktopSalesOrderListPage;
    const SalesOrderDetailPage = viewport === 'mobile' ? MobileSalesOrderDetailPage : DesktopSalesOrderDetailPage;
    const DeliveryNoteListPage = viewport === 'mobile' ? MobileDeliveryNoteListPage : DesktopDeliveryNoteListPage;
    const InvoiceListPage = viewport === 'mobile' ? MobileInvoiceListPage : DesktopInvoiceListPage;
    const PaymentListPage = viewport === 'mobile' ? MobilePaymentListPage : DesktopPaymentListPage;
    const ProductionOrderListPage = viewport === 'mobile' ? MobileProductionOrderListPage : DesktopProductionOrderListPage;
    const ProductionOrderDetailPage = viewport === 'mobile' ? MobileProductionOrderDetailPage : DesktopProductionOrderDetailPage;
    const BomListPage = viewport === 'mobile' ? MobileBomListPage : DesktopBomListPage;
    const MaterialIssueListPage = viewport === 'mobile' ? MobileMaterialIssueListPage : DesktopMaterialIssueListPage;
    const ProductionOutputListPage = viewport === 'mobile' ? MobileProductionOutputListPage : DesktopProductionOutputListPage;
    const CompanyListPage = viewport === 'mobile' ? MobileCompanyListPage : DesktopCompanyListPage;
    const ProjectSettingsPage = viewport === 'mobile' ? MobileProjectSettingsPage : DesktopProjectSettingsPage;
    const UserListPage = viewport === 'mobile' ? MobileUserListPage : DesktopUserListPage;
    const IntegrationsPage = viewport === 'mobile' ? MobileIntegrationsPage : DesktopIntegrationsPage;
    const VatPage = viewport === 'mobile' ? MobileVatPage : DesktopVatPage;

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
                <Route path="/app" element={<DashboardPage currentUserId={currentUserId} userName={userName} />} />
                <Route path="/app/purchase/suppliers" element={<SupplierListPage />} />
                <Route path="/app/purchase/pipeline" element={(
                    <PipelineBoardPage
                        currentUserId={currentUserId}
                        canViewAllPurchaseRequests={canViewAllPurchaseRequests}
                        canViewActivePipeline={canViewActivePipeline}
                        canViewOwnPurchaseRequests={canViewOwnPurchaseRequests}
                    />
                )} />
                <Route path="/app/purchase/pipeline/:id" element={<PipelinePage />} />
                <Route path="/app/purchase/orders" element={<PurchaseOrderListPage />} />
                <Route path="/app/purchase/orders/:id" element={<PurchaseOrderDetailPage />} />
                <Route path="/app/purchase/grns" element={<GrnListPage />} />
                <Route path="/app/purchase/grns/:id" element={<GrnDetailPage />} />
                <Route path="/app/purchase/invoices" element={<SupplierInvoiceListPage />} />
                <Route path="/app/purchase/payments" element={<SupplierPaymentListPage />} />
                <Route path="/app/inventory/items" element={<ItemListPage />} />
                <Route path="/app/inventory/warehouses" element={<WarehouseListPage />} />
                <Route path="/app/inventory/movements" element={<StockMovementPage />} />
                <Route path="/app/inventory/reports/summary" element={<StockSummaryPage />} />
                <Route path="/app/inventory/reports/movement" element={<MovementReportPage />} />
                <Route path="/app/inventory/reports/low-stock" element={<LowStockPage />} />
                <Route path="/app/inventory/reports/valuation" element={<ValuationPage />} />
                <Route path="/app/sales/customers" element={<CustomerListPage />} />
                <Route path="/app/sales/orders" element={<SalesOrderListPage />} />
                <Route path="/app/sales/orders/:id" element={<SalesOrderDetailPage />} />
                <Route path="/app/sales/delivery-notes" element={<DeliveryNoteListPage />} />
                <Route path="/app/sales/invoices" element={<InvoiceListPage />} />
                <Route path="/app/sales/payments" element={<PaymentListPage />} />
                <Route path="/app/production/orders" element={<ProductionOrderListPage />} />
                <Route path="/app/production/orders/:id" element={<ProductionOrderDetailPage />} />
                <Route path="/app/production/bom" element={<BomListPage />} />
                <Route path="/app/production/material-issues" element={<MaterialIssueListPage />} />
                <Route path="/app/production/outputs" element={<ProductionOutputListPage />} />
                <Route path="/app/settings/companies" element={<CompanyListPage />} />
                <Route path="/app/settings/projects" element={<ProjectSettingsPage />} />
                <Route path="/app/settings/users" element={<UserListPage />} />
                <Route path="/app/settings/integrations" element={<IntegrationsPage />} />
                <Route path="/app/settings/vat" element={<VatPage />} />
                <Route path="*" element={<div>Page not found.</div>} />
            </Routes>
        </AppShell>
    );
}
