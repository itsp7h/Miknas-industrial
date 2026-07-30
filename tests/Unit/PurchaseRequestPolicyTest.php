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
        $own   = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);
        $other = PurchaseRequest::factory()->create();

        $this->assertTrue($requester->can('view', $own));
        $this->assertFalse($requester->can('view', $other));
    }

    public function test_requester_can_update_own_draft_but_not_after_draft_or_someone_elses(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownDraft = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);
        $ownRfq   = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);
        $othersDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($requester->can('update', $ownDraft));
        $this->assertFalse($requester->can('update', $ownRfq));
        $this->assertFalse($requester->can('update', $othersDraft));
    }

    public function test_purchase_manager_can_approve_only_at_gm_approval_stage(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $atStage    = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);
        $notAtStage = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($manager->can('approve', $atStage));
        $this->assertFalse($manager->can('approve', $notAtStage));
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

    public function test_procurement_officer_can_manage_rfq_only_at_rfq_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atRfq  = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $atDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->assertTrue($procurement->can('manageRfq', $atRfq));
        $this->assertFalse($procurement->can('manageRfq', $atDraft));
    }

    public function test_procurement_officer_can_award_at_comparison_or_lpo_stage(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $atComparison = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $atLpo        = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $atRfq        = PurchaseRequest::factory()->create(['stage' => 'rfq']);

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
