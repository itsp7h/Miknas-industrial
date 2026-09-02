<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The procurement gates. Every one of these actions is a React dialog on the
 * pipeline detail page now, so each is asserted against the API endpoint the
 * dialog calls — the policy behind them is unchanged.
 */
class ProcurementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** The payload the supplier-select dialog posts. */
    private function selectPayload(Supplier $supplier): array
    {
        return ['mode' => 'global', 'supplier_ids' => [$supplier->id]];
    }

    public function test_user_without_manage_rfq_cannot_select_suppliers(): void
    {
        $user = User::factory()->create();
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/pipeline/{$atRfq->id}/suppliers", $this->selectPayload($supplier))
            ->assertForbidden();
    }

    public function test_procurement_officer_can_select_suppliers_at_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($procurement)
            ->postJson("/api/v1/purchase/pipeline/{$atRfq->id}/suppliers", $this->selectPayload($supplier))
            ->assertOk();
    }

    public function test_procurement_officer_cannot_select_suppliers_before_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($procurement)
            ->postJson("/api/v1/purchase/pipeline/{$atDraft->id}/suppliers", $this->selectPayload($supplier))
            ->assertForbidden();
    }

    /**
     * The award endpoints moved to the API with the quotes workspace; the gate
     * is the same policy. QuoteWorkspaceTest covers the rest of the flow.
     */
    public function test_user_without_award_permission_cannot_award_an_item(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $quote = SupplierQuote::factory()->create(['purchase_request_id' => $pr->id]);
        $quoteItem = SupplierQuoteItem::factory()->create(['supplier_quote_id' => $quote->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/requests/{$pr->id}/quotes/items/{$quoteItem->id}/award", [
                'award_reason' => 'Cheapest by a mile',
            ])->assertForbidden();
    }

    public function test_user_without_generate_lpo_permission_cannot_generate_it(): void
    {
        $user = User::factory()->create();
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/pipeline/{$atLpo->id}/lpo")
            ->assertForbidden();
    }

    /**
     * The RFQ page's own `view` gate is now the pipeline detail endpoint's —
     * the supplier picker is a dialog on that page rather than a page of its
     * own.
     */
    public function test_unauthorized_user_cannot_view_the_rfq_page(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->actingAs($user)->getJson("/api/v1/purchase/pipeline/{$pr->id}")->assertForbidden();
    }

    public function test_procurement_officer_can_view_the_rfq_page_at_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $pr = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->actingAs($procurement)->getJson("/api/v1/purchase/pipeline/{$pr->id}")->assertOk();
    }

    public function test_procurement_officer_can_select_suppliers_at_gm_approval_precondition_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atGmApproval = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($procurement)
            ->postJson("/api/v1/purchase/pipeline/{$atGmApproval->id}/suppliers", $this->selectPayload($supplier))
            ->assertOk();
    }

    public function test_procurement_officer_can_reach_manage_quotes_actions_at_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->assertTrue($procurement->can('manageQuotes', $atLpo));
    }
}
