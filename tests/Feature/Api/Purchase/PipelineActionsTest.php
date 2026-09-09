<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The five actions the Blade pipeline detail page carried as modals and bare
 * POSTs — supplier selection, sending the invitations, the GM signature and
 * issuing the LPO. They had no endpoints of their own until the page moved into
 * the shell.
 */
class PipelineActionsTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Procurement Officer');

        return $user;
    }

    /** The payload the reject confirm step posts. */
    private function rejection(string $reason = 'Quantities exceed the project budget.'): array
    {
        return ['rejection_reason' => $reason];
    }

    private function approver(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Purchase Manager');
        $user->givePermissionTo('purchase-requests.approve');

        return $user;
    }

    private function request(string $stage = 'gm_approval'): PurchaseRequest
    {
        return PurchaseRequest::factory()->create(['stage' => $stage]);
    }

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::factory()->create($overrides + [
            'email' => 'supplier@example.test', 'phone' => '+97333000000', 'is_active' => true,
        ]);
    }

    public function test_every_action_requires_the_matching_permission(): void
    {
        $pr = $this->request();
        $urls = [
            "/api/v1/purchase/pipeline/{$pr->id}/suppliers",
            "/api/v1/purchase/pipeline/{$pr->id}/send-invitations",
            "/api/v1/purchase/pipeline/{$pr->id}/lpo",
            "/api/v1/purchase/pipeline/{$pr->id}/signature",
        ];

        // Unauthenticated first: actingAs() persists for the rest of the test.
        foreach ($urls as $url) {
            $this->postJson($url)->assertUnauthorized();
        }
        $this->getJson("/api/v1/purchase/pipeline/{$pr->id}/form-options")->assertUnauthorized();

        $nobody = User::factory()->create();
        foreach ($urls as $url) {
            $this->actingAs($nobody)->postJson($url)->assertForbidden();
        }
        $this->actingAs($nobody)
            ->getJson("/api/v1/purchase/pipeline/{$pr->id}/form-options")->assertForbidden();
    }

    public function test_form_options_lists_active_suppliers_and_who_is_already_invited(): void
    {
        $pr = $this->request();
        $invited = $this->supplier(['name' => 'Already In']);
        $fresh = $this->supplier(['name' => 'Not Yet', 'phone' => null]);
        $this->supplier(['name' => 'Dormant', 'is_active' => false]);
        PurchaseRequestItem::create(['purchase_request_id' => $pr->id, 'description' => 'Steel plate', 'quantity_required' => 1]);
        RfqInvitation::factory()->create(['purchase_request_id' => $pr->id, 'supplier_id' => $invited->id]);

        $response = $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/pipeline/{$pr->id}/form-options")->assertOk();

        $this->assertSame(['Already In', 'Not Yet'], array_column($response->json('suppliers'), 'name'));
        $this->assertSame([$invited->id], $response->json('selected_supplier_ids'));
        // The channel pickers key off these, so a supplier with no phone cannot
        // be offered WhatsApp.
        $this->assertFalse($response->json('suppliers.1.can_whatsapp'));
        $this->assertTrue($response->json('suppliers.1.can_email'));
        $this->assertSame('Steel plate', $response->json('items.0.description'));
    }

    public function test_selecting_suppliers_for_the_whole_request_creates_pending_invitations(): void
    {
        $pr = $this->request();
        $one = $this->supplier(['name' => 'One']);
        $two = $this->supplier(['name' => 'Two']);

        $response = $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/suppliers", [
                'mode' => 'global',
                'supplier_ids' => [$one->id, $two->id],
                'channels' => [$one->id => 'whatsapp'],
            ])->assertOk();

        $this->assertSame('rfq', $pr->fresh()->stage);
        $this->assertSame(2, $response->json('data.pending_invitation_count'));
        $this->assertDatabaseHas('rfq_invitations', ['supplier_id' => $one->id, 'channel' => 'whatsapp', 'status' => 'pending']);
        // Unnamed channels fall back to email, as the Blade form did.
        $this->assertDatabaseHas('rfq_invitations', ['supplier_id' => $two->id, 'channel' => 'email']);
    }

    public function test_selecting_by_item_records_which_items_each_supplier_was_asked_for(): void
    {
        $pr = $this->request();
        $itemA = PurchaseRequestItem::create(['purchase_request_id' => $pr->id, 'description' => 'Plate', 'quantity_required' => 1]);
        $itemB = PurchaseRequestItem::create(['purchase_request_id' => $pr->id, 'description' => 'Angle', 'quantity_required' => 2]);
        $one = $this->supplier(['name' => 'One']);
        $two = $this->supplier(['name' => 'Two']);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/suppliers", [
                'mode' => 'by_item',
                'item_suppliers' => [$itemA->id => [$one->id], $itemB->id => [$one->id, $two->id]],
            ])->assertOk();

        $this->assertSame('rfq', $pr->fresh()->stage);
        $this->assertDatabaseCount('rfq_invitations', 2);
    }

    public function test_by_item_selection_with_nothing_assigned_is_refused(): void
    {
        $pr = $this->request();

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/suppliers", ['mode' => 'by_item', 'item_suppliers' => []])
            ->assertStatus(422)->assertJsonValidationErrors('item_suppliers');

        $this->assertDatabaseCount('rfq_invitations', 0);
    }

    public function test_global_selection_with_no_suppliers_is_refused(): void
    {
        $pr = $this->request();

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/suppliers", ['mode' => 'global', 'supplier_ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('supplier_ids');
    }

    /** Choosing the same supplier twice would send them two quote links. */
    public function test_a_supplier_already_invited_is_skipped_rather_than_duplicated(): void
    {
        $pr = $this->request();
        $supplier = $this->supplier();
        RfqInvitation::factory()->create(['purchase_request_id' => $pr->id, 'supplier_id' => $supplier->id]);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/suppliers", [
                'mode' => 'global', 'supplier_ids' => [$supplier->id],
            ])->assertOk();

        $this->assertDatabaseCount('rfq_invitations', 1);
    }

    public function test_sending_invitations_marks_them_sent_and_advances_to_quoting(): void
    {
        Notification::fake();
        $pr = $this->request('rfq');
        RfqInvitation::factory()->count(2)->create([
            'purchase_request_id' => $pr->id, 'supplier_id' => $this->supplier()->id, 'status' => 'pending',
        ]);

        $response = $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/send-invitations")->assertOk();

        $this->assertSame('quoting', $pr->fresh()->stage);
        $this->assertSame(0, $response->json('data.pending_invitation_count'));
        $this->assertSame(2, $response->json('data.sent_invitation_count'));
    }

    public function test_sending_with_nothing_pending_is_refused(): void
    {
        $pr = $this->request('rfq');

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/send-invitations")->assertStatus(422);

        $this->assertSame('rfq', $pr->fresh()->stage);
    }

    public function test_the_signature_is_recorded_with_who_signed_and_advances_the_stage(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $image = 'data:image/png;base64,iVBORw0KGgo=';

        $response = $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => $image])
            ->assertOk();

        $this->assertSame($approver->name, $response->json('data.signature.signed_by_name'));
        $this->assertSame($image, $response->json('data.signature.image'));
        $this->assertDatabaseHas('purchase_signatures', [
            'purchase_request_id' => $pr->id, 'signed_by' => $approver->id,
        ]);
        // The stage machine moves it on rather than the page deciding.
        $this->assertNotSame('gm_approval', $pr->fresh()->stage);
    }

    /**
     * Signing IS the GM approval. Before this, nothing wrote these columns —
     * the Blade `approve` action was their only writer and lost its UI in the
     * cutover — so every signed request stayed 'pending' for ever and the MPR
     * sheet's approval block could never appear.
     */
    public function test_signing_records_the_approval_itself(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $this->assertSame('pending', $pr->status);

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => 'data:image/png;base64,iVBORw0KGgo='])
            ->assertOk();

        $pr->refresh();
        $this->assertSame('approved', $pr->status);
        $this->assertSame($approver->id, $pr->approved_by);
        $this->assertNotNull($pr->approved_at);
    }

    public function test_the_approval_shows_on_the_mpr_sheet_once_signed(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $approver->givePermissionTo('purchase-requests.view-all');

        $this->actingAs($approver)
            ->getJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk()->assertJsonPath('data.approval', null);

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => 'data:image/png;base64,iVBORw0KGgo='])
            ->assertOk();

        $this->actingAs($approver)
            ->getJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approval.approved_by_name', $approver->name);
    }

    // ── Rejection: the other half of the same gate ──────────────────────────

    public function test_rejecting_needs_the_same_permission_as_signing(): void
    {
        $pr = $this->request('gm_approval');

        $this->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())
            ->assertForbidden();
    }

    public function test_rejecting_records_the_refusal_and_leaves_the_stage_alone(): void
    {
        $pr = $this->request('gm_approval');

        $response = $this->actingAs($this->approver())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())
            ->assertOk();

        $pr->refresh();
        $this->assertSame('rejected', $pr->status);
        // A rejected request stops where it is rather than travelling on.
        $this->assertSame('gm_approval', $pr->stage);
        $this->assertStringContainsString('rejected', $response->json('message'));
        $response->assertJsonPath('data.status', 'rejected');
    }

    /**
     * `approved_by`/`approved_at` mean what they say. Writing them on a refusal
     * would make the MPR sheet print "Approved By" over it.
     */
    public function test_rejecting_does_not_claim_anyone_approved_it(): void
    {
        $pr = $this->request('gm_approval');

        $this->actingAs($this->approver())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())->assertOk();

        $pr->refresh();
        $this->assertNull($pr->approved_by);
        $this->assertNull($pr->approved_at);
    }

    /**
     * A request approved and then rejected still carries approved_by, so the
     * sheet's approval block has to key off the status rather than the
     * relation — otherwise it prints an approval over a refusal.
     */
    public function test_the_sheet_hides_the_approval_block_once_a_signed_request_is_rejected(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $approver->givePermissionTo('purchase-requests.view-all');

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => 'data:image/png;base64,iVBORw0KGgo='])
            ->assertOk();

        // Back to an approvable stage so the policy allows the refusal.
        $pr->refresh()->update(['stage' => 'gm_approval']);

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())->assertOk();

        $this->assertNotNull($pr->fresh()->approved_by);
        $this->actingAs($approver)
            ->getJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.approval', null);
    }

    /**
     * The reason is what the requester reads to know what to change, so a
     * refusal without one is not accepted — the same rule an award follows.
     */
    public function test_rejecting_requires_a_reason(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", [])
            ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", ['rejection_reason' => 'no'])
            ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');

        // Nothing was written by either attempt.
        $this->assertSame('pending', $pr->fresh()->status);
    }

    public function test_the_reason_is_recorded_with_who_refused_it_and_when(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();

        $response = $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection('Over budget for this project.'))
            ->assertOk();

        $pr->refresh();
        $this->assertSame('Over budget for this project.', $pr->rejection_reason);
        $this->assertSame($approver->id, $pr->rejected_by);
        $this->assertNotNull($pr->rejected_at);

        // The detail page gets it back in the same response.
        $response->assertJsonPath('data.rejection.reason', 'Over budget for this project.');
        $response->assertJsonPath('data.rejection.rejected_by_name', $approver->name);
    }

    public function test_the_reason_shows_on_the_mpr_sheet_while_rejected(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $approver->givePermissionTo('purchase-requests.view-all');

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection('Over budget for this project.'))
            ->assertOk();

        $this->actingAs($approver)
            ->getJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk()
            ->assertJsonPath('data.rejection.reason', 'Over budget for this project.')
            ->assertJsonPath('data.rejection.rejected_by_name', $approver->name);
    }

    /**
     * The record is kept as history but must stop being presented as the
     * current state — the same rule the approval block follows in reverse.
     */
    public function test_the_rejection_is_hidden_once_the_request_is_approved_after_all(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $approver->givePermissionTo('purchase-requests.view-all');

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())->assertOk();
        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => 'data:image/png;base64,iVBORw0KGgo='])
            ->assertOk();

        // Still on the row …
        $this->assertNotNull($pr->fresh()->rejection_reason);

        // … but not in either payload.
        $this->actingAs($approver)
            ->getJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk()
            ->assertJsonPath('data.rejection', null)
            ->assertJsonPath('data.approval.approved_by_name', $approver->name);

        $this->actingAs($approver)
            ->getJson("/api/v1/purchase/pipeline/{$pr->id}")
            ->assertOk()->assertJsonPath('data.rejection', null);
    }

    public function test_a_request_cannot_be_rejected_twice(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();

        $this->actingAs($approver)->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())->assertOk();
        $this->actingAs($approver)->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())->assertStatus(422);
    }

    /** Rejecting is not final: the GM can still sign it afterwards. */
    public function test_a_rejected_request_can_still_be_approved(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();

        $this->actingAs($approver)->postJson("/api/v1/purchase/pipeline/{$pr->id}/reject", $this->rejection())->assertOk();
        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => 'data:image/png;base64,iVBORw0KGgo='])
            ->assertOk();

        $this->assertSame('approved', $pr->fresh()->status);
    }

    public function test_a_signature_requires_an_image(): void
    {
        $pr = $this->request('gm_approval');

        $this->actingAs($this->approver())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", [])
            ->assertStatus(422)->assertJsonValidationErrors('signature_image');
    }

    /**
     * Signing twice would overwrite who approved it and when. Two guards stop
     * it: the approve policy only holds at draft/gm_approval, and signing
     * advances the stage past both — so the second attempt is refused by policy
     * (403). The controller's own already-signed check covers the case where a
     * request is somehow back at an approvable stage with a signature on it.
     */
    public function test_a_request_cannot_be_signed_twice(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $image = 'data:image/png;base64,iVBORw0KGgo=';

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => $image])->assertOk();
        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => $image])
            ->assertForbidden();

        $this->assertDatabaseCount('purchase_signatures', 1);
    }

    public function test_the_already_signed_guard_holds_when_the_stage_allows_approval(): void
    {
        $pr = $this->request('gm_approval');
        $approver = $this->approver();
        $image = 'data:image/png;base64,iVBORw0KGgo=';

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => $image])->assertOk();

        // Put it back where the policy allows signing; the controller must still
        // refuse a second signature. The refresh matters: the instance still
        // holds the pre-signature stage, so update() would see no change and
        // issue no query.
        $pr->refresh()->update(['stage' => 'gm_approval']);

        $this->actingAs($approver)
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/signature", ['signature_image' => $image])
            ->assertStatus(422);

        $this->assertDatabaseCount('purchase_signatures', 1);
    }

    /**
     * The generation service refuses when nothing is awarded. That is a message
     * for the user, not a 500 — the Blade page flashed it and stayed put.
     */
    public function test_issuing_an_lpo_with_nothing_awarded_is_refused_with_a_message(): void
    {
        $pr = $this->request('lpo');

        $response = $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$pr->id}/lpo")->assertStatus(422);

        $this->assertNotEmpty($response->json('message'));
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertSame('lpo', $pr->fresh()->stage);
    }
}
