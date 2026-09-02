<?php

namespace Tests\Unit;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_view_own_request_but_not_others(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $own = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);
        $other = PurchaseRequest::factory()->create();

        $this->assertTrue($requester->can('view', $own));
        $this->assertFalse($requester->can('view', $other));
    }

    public function test_requester_can_update_own_draft_but_not_after_draft_or_someone_elses(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownDraft = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);
        $ownRfq = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);
        $othersDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($requester->can('update', $ownDraft));
        $this->assertFalse($requester->can('update', $ownRfq));
        $this->assertFalse($requester->can('update', $othersDraft));
    }

    public function test_purchase_manager_can_approve_at_draft_or_gm_approval_stage_but_not_later(): void
    {
        // 'draft' is the real-world precondition: the signature endpoint
        // is authorized against 'approve' while the request is still at draft, and
        // only advances it to gm_approval afterwards. 'gm_approval' is also allowed
        // so a re-check against an already-advanced request still passes.
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $atDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $atGmApproval = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->assertTrue($manager->can('approve', $atDraft));
        $this->assertTrue($manager->can('approve', $atGmApproval));
        $this->assertFalse($manager->can('approve', $atRfq));
    }

    public function test_purchase_manager_can_view_all_but_cannot_award(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $anyRequest = PurchaseRequest::factory()->create(['stage' => 'comparison']);

        $this->assertTrue($manager->can('view', $anyRequest));
        $this->assertFalse($manager->can('award', $anyRequest));
    }

    public function test_procurement_officer_cannot_see_draft_stage_requests(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $draft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->assertFalse($procurement->can('view', $draft));
        $this->assertTrue($procurement->can('view', $atRfq));
    }

    public function test_procurement_officer_can_manage_rfq_at_gm_approval_or_rfq_stage_but_not_draft(): void
    {
        // 'gm_approval' is the real-world precondition: the select-suppliers endpoint
        // is authorized against 'manageRfq' right after the GM signature advances the
        // request to gm_approval, and it is itself the action that sets stage to 'rfq'.
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atGmApproval = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $atDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($procurement->can('manageRfq', $atGmApproval));
        $this->assertTrue($procurement->can('manageRfq', $atRfq));
        $this->assertFalse($procurement->can('manageRfq', $atDraft));
    }

    public function test_procurement_officer_can_manage_quotes_at_quoting_comparison_or_lpo_stage(): void
    {
        // manageQuotes gates the comparison workspace, which also contains the
        // "Re-issue LPO" affordance — so it must stay open through 'lpo' or that
        // affordance becomes unreachable once award() itself allows 'lpo'.
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atQuoting = PurchaseRequest::factory()->create(['stage' => 'quoting']);
        $atComparison = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->assertTrue($procurement->can('manageQuotes', $atQuoting));
        $this->assertTrue($procurement->can('manageQuotes', $atComparison));
        $this->assertTrue($procurement->can('manageQuotes', $atLpo));
        $this->assertFalse($procurement->can('manageQuotes', $atRfq));
    }

    public function test_requester_can_delete_own_draft_request_but_not_after_draft_or_someone_elses(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownDraft = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);
        $ownRfq = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);
        $othersDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($requester->can('delete', $ownDraft));
        $this->assertFalse($requester->can('delete', $ownRfq));
        $this->assertFalse($requester->can('delete', $othersDraft));
    }

    public function test_procurement_officer_can_award_at_comparison_or_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atComparison = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->assertTrue($procurement->can('award', $atComparison));
        $this->assertTrue($procurement->can('award', $atLpo));
        $this->assertFalse($procurement->can('award', $atRfq));
    }

    public function test_procurement_officer_can_generate_lpo_only_at_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atLpo = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $atComparison = PurchaseRequest::factory()->create(['stage' => 'comparison']);

        $this->assertTrue($procurement->can('generateLpo', $atLpo));
        $this->assertFalse($procurement->can('generateLpo', $atComparison));
    }

    public function test_admin_bypasses_every_ability_regardless_of_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $anyRequest = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($admin->can('view', $anyRequest));
        $this->assertTrue($admin->can('update', $anyRequest));
        $this->assertTrue($admin->can('delete', $anyRequest));
        $this->assertTrue($admin->can('approve', $anyRequest));
        $this->assertTrue($admin->can('manageRfq', $anyRequest));
        $this->assertTrue($admin->can('award', $anyRequest));
        $this->assertTrue($admin->can('generateLpo', $anyRequest));
    }

    public function test_custom_toggle_grants_access_beyond_profile(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $requester->givePermissionTo('purchase-requests.view-all');
        $othersRequest = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->assertTrue($requester->can('view', $othersRequest));
    }

    public function test_user_with_no_profile_or_permissions_can_do_nothing(): void
    {
        $bystander = User::factory()->create();
        $anyRequest = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertFalse($bystander->can('view', $anyRequest));
        $this->assertFalse($bystander->can('create', PurchaseRequest::class));
    }
}
