<?php

namespace Tests\Unit;

use App\Models\PurchaseRequest;
use Database\Seeders\PurchaseAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseAccessSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_ten_permissions(): void
    {
        (new PurchaseAccessSeeder)->run();

        $this->assertCount(10, Permission::all());
        $this->assertTrue(Permission::where('name', 'purchase-requests.create')->exists());
        $this->assertTrue(Permission::where('name', 'purchase-requests.generate-lpo')->exists());
    }

    public function test_seeds_three_profiles_with_correct_permissions(): void
    {
        (new PurchaseAccessSeeder)->run();

        $requester = Role::where('name', 'Requester')->first();
        $this->assertNotNull($requester);
        $this->assertEqualsCanonicalizing(
            ['purchase-requests.create', 'purchase-requests.edit', 'purchase-requests.view-own'],
            $requester->permissions->pluck('name')->all()
        );

        $manager = Role::where('name', 'Purchase Manager')->first();
        $this->assertEqualsCanonicalizing(
            ['purchase-requests.approve', 'purchase-requests.view-all'],
            $manager->permissions->pluck('name')->all()
        );

        $procurement = Role::where('name', 'Procurement Officer')->first();
        $this->assertEqualsCanonicalizing(
            [
                'purchase-requests.manage-rfq',
                'purchase-requests.manage-quotes',
                'purchase-requests.award',
                'purchase-requests.generate-lpo',
                'purchase-requests.view-active-pipeline',
            ],
            $procurement->permissions->pluck('name')->all()
        );
    }

    public function test_running_twice_does_not_duplicate_or_error(): void
    {
        (new PurchaseAccessSeeder)->run();
        (new PurchaseAccessSeeder)->run();

        $this->assertCount(10, Permission::all());
        $this->assertCount(3, Role::whereIn('name', ['Requester', 'Purchase Manager', 'Procurement Officer'])->get());
    }

    public function test_purchase_request_factory_produces_a_valid_draft_request(): void
    {
        $pr = PurchaseRequest::factory()->create();

        $this->assertSame('draft', $pr->stage);
        $this->assertSame('pending', $pr->status);
        $this->assertNotNull($pr->requested_by);
    }
}
