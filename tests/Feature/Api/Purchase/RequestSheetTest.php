<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\PurchaseRequestDeleted;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The MPR sheet — the read-only request page, formerly
 * resources/views/purchase/requests/show.blade.php.
 */
class RequestSheetTest extends TestCase
{
    use RefreshDatabase;

    private function requester(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Requester');

        return $user;
    }

    private function sheet(User $owner, array $overrides = []): PurchaseRequest
    {
        $pr = PurchaseRequest::factory()->create(array_merge([
            'requested_by' => $owner->id,
            'stage' => 'draft',
            'status' => 'pending',
            'project_name' => 'Plant Expansion',
            'requested_by_name' => 'Aisha Rahman',
            'required_date_text' => '1 Week',
            'location' => 'Bay 4',
            'department' => 'Operations',
            'remarks' => 'Before the shutdown.',
        ], $overrides));

        $pr->items()->create([
            'description' => 'Steel Plate 10mm', 'unit' => 'KG',
            'quantity_required' => 500, 'purpose_use' => 'Frame', 'required_date' => '2026-09-10',
        ]);

        return $pr;
    }

    public function test_the_sheet_is_closed_to_anyone_who_cannot_view_the_request(): void
    {
        $pr = PurchaseRequest::factory()->create();

        $this->getJson("/api/v1/purchase/requests/{$pr->id}")->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertForbidden();
    }

    public function test_the_sheet_carries_every_field_the_blade_page_printed(): void
    {
        $requester = $this->requester();
        $pr = $this->sheet($requester);

        $response = $this->actingAs($requester)
            ->getJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk();

        $response->assertJsonPath('data.request_number', $pr->request_number);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.project_name', 'Plant Expansion');
        $response->assertJsonPath('data.requested_by_name', 'Aisha Rahman');
        $response->assertJsonPath('data.required_date_text', '1 Week');
        $response->assertJsonPath('data.location', 'Bay 4');
        $response->assertJsonPath('data.department', 'Operations');
        // Remarks never appeared in the pipeline detail payload, which is why
        // the sheet has one of its own.
        $response->assertJsonPath('data.remarks', 'Before the shutdown.');

        $response->assertJsonPath('data.items.0.description', 'Steel Plate 10mm');
        $response->assertJsonPath('data.items.0.unit', 'KG');
        $response->assertJsonPath('data.items.0.purpose_use', 'Frame');
        $response->assertJsonPath('data.items.0.required_date', '2026-09-10');

        // The MPR document stays a DomPDF page, so the sheet links it.
        $this->assertStringContainsString("/purchase/requests/{$pr->id}/print", $response->json('data.print_url'));
    }

    public function test_the_approval_block_appears_only_once_approved(): void
    {
        $requester = $this->requester();
        $pending = $this->sheet($requester);

        $this->actingAs($requester)
            ->getJson("/api/v1/purchase/requests/{$pending->id}")
            ->assertOk()
            ->assertJsonPath('data.approval', null);

        $approver = User::factory()->create(['name' => 'Khalid Nasser']);
        $approved = $this->sheet($requester, [
            'status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => '2026-09-01 14:07:00',
        ]);

        $this->actingAs($requester)
            ->getJson("/api/v1/purchase/requests/{$approved->id}")
            ->assertOk()
            ->assertJsonPath('data.approval.approved_by_name', 'Khalid Nasser');
    }

    public function test_the_sheet_reports_whether_this_user_may_edit_or_delete(): void
    {
        $requester = $this->requester();
        $own = $this->sheet($requester);

        $this->actingAs($requester)
            ->getJson("/api/v1/purchase/requests/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions.update', true)
            ->assertJsonPath('data.permissions.delete', true);

        // Past draft, neither is allowed any more.
        $own->refresh()->update(['stage' => 'rfq']);

        $this->actingAs($requester)
            ->getJson("/api/v1/purchase/requests/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions.update', false)
            ->assertJsonPath('data.permissions.delete', false);
    }

    public function test_deleting_a_request_takes_its_items_and_tells_the_boards(): void
    {
        Event::fake([PurchaseRequestDeleted::class]);
        $requester = $this->requester();
        $pr = $this->sheet($requester);

        $response = $this->actingAs($requester)
            ->deleteJson("/api/v1/purchase/requests/{$pr->id}")
            ->assertOk();

        $this->assertStringContainsString('deleted', $response->json('message'));
        $this->assertDatabaseMissing('purchase_requests', ['id' => $pr->id]);
        // The item rows go with it — the foreign key cascades.
        $this->assertDatabaseMissing('purchase_request_items', ['purchase_request_id' => $pr->id]);
        Event::assertDispatched(fn (PurchaseRequestDeleted $event) => $event->purchaseRequestId === $pr->id);
    }
}
