<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Services\PurchaseStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchasePipelineShowTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-all');

        return $user;
    }

    public function test_it_requires_authentication(): void
    {
        $request = PurchaseRequest::factory()->create();

        $this->getJson("/api/v1/purchase/pipeline/{$request->id}")->assertUnauthorized();
    }

    /** A user with no view permission at all must not read a request. */
    public function test_it_forbids_a_user_who_cannot_view_the_request(): void
    {
        $request = PurchaseRequest::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/purchase/pipeline/{$request->id}")
            ->assertForbidden();
    }

    public function test_view_own_permission_cannot_read_someone_elses_request(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-own');
        $theirs = PurchaseRequest::factory()->create(['requested_by' => User::factory()->create()->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/purchase/pipeline/{$theirs->id}")
            ->assertForbidden();
    }

    /**
     * The progress bar and timeline are driven by these, computed from
     * PurchaseStageService rather than duplicated in JS.
     */
    public function test_it_reports_the_stage_index_and_progress_from_the_stage_service(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $stages = PurchaseStageService::STAGES;
        $expectedIndex = array_search('lpo', $stages, true);

        $response = $this->actingAs($this->viewer())
            ->getJson("/api/v1/purchase/pipeline/{$request->id}")
            ->assertOk();

        $response->assertJsonPath('data.stage_index', $expectedIndex);
        $response->assertJsonPath(
            'data.progress_pct',
            (int) round($expectedIndex / (count($stages) - 1) * 100)
        );
        $response->assertJsonPath('data.is_done', false);
        $response->assertJsonPath('data.stage_labels.lpo', 'LPO Issued');
        $this->assertSame($stages, $response->json('data.stages'));
    }

    public function test_a_complete_request_reports_full_progress_and_is_done(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'complete']);

        $this->actingAs($this->viewer())
            ->getJson("/api/v1/purchase/pipeline/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.progress_pct', 100)
            ->assertJsonPath('data.is_done', true);
    }

    /**
     * Permissions are resolved server-side from PurchaseRequestPolicy so the
     * page never re-implements the gates. A plain viewer can act on nothing.
     */
    public function test_it_resolves_permissions_server_side(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->actingAs($this->viewer())
            ->getJson("/api/v1/purchase/pipeline/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions.generateLpo', false)
            ->assertJsonPath('data.permissions.approve', false);

        $officer = User::factory()->create();
        $officer->assignRole('Procurement Officer');

        $this->actingAs($officer)
            ->getJson("/api/v1/purchase/pipeline/{$request->id}")
            ->assertOk()
            // generateLpo is stage-scoped and this request is at 'lpo'.
            ->assertJsonPath('data.permissions.generateLpo', true);
    }

    public function test_it_summarises_invitations_items_and_quotes_for_the_sidebar(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $cheap = Supplier::factory()->create(['name' => 'Cheap Co']);
        $dear = Supplier::factory()->create(['name' => 'Dear Co']);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $request->id,
            'description' => 'Steel Plate',
            'unit' => 'KG',
            'quantity_required' => 10,
        ]);

        foreach ([[$cheap, 'submitted', 100.0, true], [$dear, 'pending', 200.0, false]] as [$supplier, $status, $total, $awarded]) {
            $invitation = RfqInvitation::create([
                'purchase_request_id' => $request->id,
                'supplier_id' => $supplier->id,
                'token' => (string) Str::uuid(),
                'channel' => 'email',
                'status' => $status,
            ]);
            $quote = SupplierQuote::create([
                'rfq_invitation_id' => $invitation->id,
                'purchase_request_id' => $request->id,
                'supplier_id' => $supplier->id,
                'submitted_at' => now(),
                'total_amount' => $total,
            ]);
            SupplierQuoteItem::create([
                'supplier_quote_id' => $quote->id,
                'purchase_request_item_id' => $item->id,
                'description' => 'Steel Plate',
                'quantity' => 10,
                'unit_price' => $total / 10,
                'total_price' => $total,
                'not_available' => false,
                'is_awarded' => $awarded,
            ]);
        }

        $response = $this->actingAs($this->viewer())
            ->getJson("/api/v1/purchase/pipeline/{$request->id}")
            ->assertOk();

        $response->assertJsonPath('data.pending_invitation_count', 1);
        $response->assertJsonPath('data.sent_invitation_count', 1);
        $response->assertJsonPath('data.items.0.quote_count', 2);
        $response->assertJsonPath('data.items.0.is_awarded', true);
        $response->assertJsonPath('data.awarded_supplier_names.0', 'Cheap Co');

        // Quotes come back cheapest-first, as the Blade sidebar sorted them.
        $response->assertJsonPath('data.supplier_quotes.0.supplier_name', 'Cheap Co');
        $response->assertJsonPath('data.supplier_quotes.0.has_awarded_items', true);
        // The cheapest quote already has awards, so it is not flagged LOWEST.
        $response->assertJsonPath('data.supplier_quotes.0.is_lowest', false);
        $response->assertJsonPath('data.supplier_quotes.1.is_lowest', false);
    }

    /** A quote line marked not-available must not count towards an item's quotes. */
    public function test_it_excludes_unavailable_quote_lines_from_an_items_quote_count(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'quoting']);
        $supplier = Supplier::factory()->create();
        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $request->id,
            'description' => 'Bolt',
            'unit' => 'PCS',
            'quantity_required' => 5,
        ]);
        $invitation = RfqInvitation::create([
            'purchase_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'token' => (string) Str::uuid(),
            'channel' => 'email',
            'status' => 'submitted',
        ]);
        $quote = SupplierQuote::create([
            'rfq_invitation_id' => $invitation->id,
            'purchase_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'submitted_at' => now(),
            'total_amount' => 50,
        ]);
        SupplierQuoteItem::create([
            'supplier_quote_id' => $quote->id,
            'purchase_request_item_id' => $item->id,
            'description' => 'Bolt',
            'quantity' => 5,
            'unit_price' => 10,
            'total_price' => 50,
            'not_available' => true,
        ]);

        $this->actingAs($this->viewer())
            ->getJson("/api/v1/purchase/pipeline/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.quote_count', 0);
    }

    /**
     * The WhatsApp shortcut was only offered for a pending invitation while the
     * request is still at rfq/quoting — not once it has moved on.
     */
    public function test_the_whatsapp_link_is_only_offered_for_pending_invitations_in_the_rfq_stages(): void
    {
        $supplier = Supplier::factory()->create(['phone' => '+973 1700 0000']);

        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        RfqInvitation::create([
            'purchase_request_id' => $atRfq->id, 'supplier_id' => $supplier->id,
            'token' => (string) Str::uuid(), 'channel' => 'whatsapp', 'status' => 'pending',
        ]);

        $atPayment = PurchaseRequest::factory()->create(['stage' => 'payment']);
        RfqInvitation::create([
            'purchase_request_id' => $atPayment->id, 'supplier_id' => $supplier->id,
            'token' => (string) Str::uuid(), 'channel' => 'whatsapp', 'status' => 'pending',
        ]);

        $viewer = $this->viewer();

        $link = $this->actingAs($viewer)
            ->getJson("/api/v1/purchase/pipeline/{$atRfq->id}")
            ->assertOk()
            ->json('data.rfq_invitations.0.whatsapp_link');
        $this->assertStringContainsString('wa.me', $link);

        $this->actingAs($viewer)
            ->getJson("/api/v1/purchase/pipeline/{$atPayment->id}")
            ->assertOk()
            ->assertJsonPath('data.rfq_invitations.0.whatsapp_link', null);
    }
}
