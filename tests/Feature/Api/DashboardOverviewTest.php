<?php

namespace Tests\Feature\Api;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile Home and More screens' figures. Each block answers only for
 * someone who could open the page it summarises.
 */
class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/overview')->assertStatus(401);
    }

    public function test_someone_with_no_permissions_gets_no_blocks(): void
    {
        Supplier::factory()->count(2)->create();
        PurchaseRequest::factory()->create();

        $this->actingAs($this->userWith([]))
            ->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('pipeline', null)
            ->assertJsonPath('low_stock', null)
            ->assertJsonPath('actions', [])
            ->assertJsonPath('counts', []);
    }

    public function test_pipeline_counts_follow_the_boards_visibility(): void
    {
        $user = $this->userWith(['pipeline.view', 'pipeline.view-own']);
        PurchaseRequest::factory()->create(['requested_by' => $user->id, 'stage' => 'quoting']);
        PurchaseRequest::factory()->create(['requested_by' => $user->id, 'stage' => 'complete']);
        // Someone else's: the board would not show it, so neither may the count.
        PurchaseRequest::factory()->create(['stage' => 'quoting']);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/overview')->assertOk();

        $response->assertJsonPath('pipeline.active', 1)->assertJsonPath('pipeline.completed', 1);
        $quoting = collect($response->json('pipeline.stages'))->firstWhere('key', 'quoting');
        $this->assertSame(1, $quoting['count']);
    }

    public function test_an_unsigned_request_is_an_action_only_for_a_signer(): void
    {
        PurchaseRequest::factory()->create(['request_number' => 'MI-MPR-26-0004', 'department' => 'Forkoll']);

        $this->actingAs($this->userWith(['pipeline.approve']))
            ->getJson('/api/v1/dashboard/overview')
            ->assertJsonPath('actions.0.kind', 'gm_signature')
            ->assertJsonPath('actions.0.reference', 'MI-MPR-26-0004')
            ->assertJsonPath('actions.0.detail', 'Forkoll');

        $this->actingAs($this->userWith(['pipeline.view']))
            ->getJson('/api/v1/dashboard/overview')
            ->assertJsonPath('actions', []);
    }

    public function test_unpaid_invoices_are_counted_with_what_is_outstanding(): void
    {
        $supplier = Supplier::factory()->create();
        foreach ([['unpaid', 100, 0], ['partial', 50, 20], ['paid', 70, 70]] as $i => [$status, $total, $paid]) {
            SupplierInvoice::create([
                'invoice_number' => "INV-$i", 'supplier_id' => $supplier->id, 'invoice_date' => now(),
                'subtotal' => $total, 'vat_amount' => 0, 'total_amount' => $total, 'paid_amount' => $paid,
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($this->userWith(['supplier-invoices.view']))
            ->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('counts.supplier_invoices', 3)
            ->assertJsonPath('counts.unpaid_invoices', 2)
            ->assertJsonPath('actions.0.kind', 'unpaid_invoices')
            ->assertJsonPath('actions.0.count', 2);

        $this->assertEquals(130, $response->json('actions.0.outstanding'));
    }
}
