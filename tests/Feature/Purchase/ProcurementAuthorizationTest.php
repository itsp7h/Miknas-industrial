<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_manage_rfq_cannot_select_suppliers(): void
    {
        $user = User::factory()->create();
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($user)
            ->post(route('purchase.requests.rfq.select', $atRfq), ['supplier_ids' => [$supplier->id]])
            ->assertForbidden();
    }

    public function test_procurement_officer_can_select_suppliers_at_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($procurement)
            ->post(route('purchase.requests.rfq.select', $atRfq), ['supplier_ids' => [$supplier->id]])
            ->assertRedirect();
    }

    public function test_procurement_officer_cannot_select_suppliers_before_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($procurement)
            ->post(route('purchase.requests.rfq.select', $atDraft), ['supplier_ids' => [$supplier->id]])
            ->assertForbidden();
    }

    public function test_user_without_award_permission_cannot_award_an_item(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $quote = SupplierQuote::factory()->create(['purchase_request_id' => $pr->id]);
        $quoteItem = SupplierQuoteItem::factory()->create(['supplier_quote_id' => $quote->id]);

        $this->actingAs($user)
            ->post(route('purchase.requests.quotes.items.award', [$pr, $quoteItem]))
            ->assertForbidden();
    }

    public function test_user_without_generate_lpo_permission_cannot_generate_it(): void
    {
        $user = User::factory()->create();
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($user)
            ->post(route('purchase.requests.generate-lpo', $atLpo))
            ->assertForbidden();
    }
}
