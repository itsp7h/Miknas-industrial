<?php

namespace Tests\Feature\Api\Settings;

use App\Models\Settings\Company;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_the_endpoints_require_the_settings_permission(): void
    {
        $this->getJson('/api/v1/settings/company-warehouses')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/company-warehouses')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->putJson('/api/v1/settings/company-warehouses', ['links' => []])->assertForbidden();
    }

    public function test_it_lists_every_company_with_its_warehouse_and_the_choices(): void
    {
        $askar = Warehouse::create(['name' => 'Askar', 'code' => 'WH-ASKAR']);
        Warehouse::create(['name' => 'Hidd', 'code' => 'WH-HIDD', 'is_active' => false]);
        Company::create(['name' => 'Miknas Industrial', 'warehouse_id' => $askar->id, 'is_active' => true]);
        Company::create(['name' => 'Matana', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/company-warehouses')->assertOk();

        // Alphabetical, like every other settings list.
        $this->assertSame(['Matana', 'Miknas Industrial'], array_column($response->json('data'), 'name'));
        $this->assertNull($response->json('data.0.warehouse_id'));
        $this->assertSame($askar->id, $response->json('data.1.warehouse_id'));
        $this->assertSame('Askar', $response->json('data.1.warehouse_name'));

        // An inactive warehouse is still offered: a company already pointing at
        // one must not silently lose its link when the yard is deactivated.
        $this->assertSame(['Askar', 'Hidd'], array_column($response->json('warehouses'), 'name'));
    }

    public function test_it_links_and_unlinks_companies(): void
    {
        $askar = Warehouse::create(['name' => 'Askar', 'code' => 'WH-ASKAR']);
        $hidd = Warehouse::create(['name' => 'Hidd', 'code' => 'WH-HIDD']);
        $miknas = Company::create(['name' => 'Miknas Industrial', 'warehouse_id' => $hidd->id, 'is_active' => true]);
        $steel = Company::create(['name' => 'Steel Tech', 'warehouse_id' => $askar->id, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/company-warehouses', ['links' => [
                ['id' => $miknas->id, 'warehouse_id' => $askar->id],
                ['id' => $steel->id, 'warehouse_id' => null],
            ]])
            ->assertOk()
            ->assertJsonPath('message', 'Company warehouses saved.');

        $this->assertSame($askar->id, $miknas->fresh()->warehouse_id);
        $this->assertNull($steel->fresh()->warehouse_id);
    }

    /** One yard serving two companies is a real arrangement, unlike a shared code. */
    public function test_two_companies_may_share_a_warehouse(): void
    {
        $askar = Warehouse::create(['name' => 'Askar', 'code' => 'WH-ASKAR']);
        $one = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $two = Company::create(['name' => 'Matana', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/company-warehouses', ['links' => [
                ['id' => $one->id, 'warehouse_id' => $askar->id],
                ['id' => $two->id, 'warehouse_id' => $askar->id],
            ]])
            ->assertOk();

        $this->assertSame($askar->id, $one->fresh()->warehouse_id);
        $this->assertSame($askar->id, $two->fresh()->warehouse_id);
    }

    public function test_it_rejects_a_warehouse_that_does_not_exist(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/company-warehouses', ['links' => [
                ['id' => $company->id, 'warehouse_id' => 9999],
            ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('links.0.warehouse_id');
    }

    /** `on delete set null`: losing the yard unlinks the company, it does not delete it. */
    public function test_deleting_a_warehouse_unlinks_the_companies_pointing_at_it(): void
    {
        $askar = Warehouse::create(['name' => 'Askar', 'code' => 'WH-ASKAR']);
        $company = Company::create(['name' => 'Miknas Industrial', 'warehouse_id' => $askar->id, 'is_active' => true]);

        $askar->delete();

        $this->assertNotNull($company->fresh());
        $this->assertNull($company->fresh()->warehouse_id);
    }
}
