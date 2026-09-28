<?php

namespace Tests\Feature\Api\Settings;

use App\Models\Settings\Company;
use App\Models\Settings\Department;
use App\Models\Settings\ProjectSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    /** The Blade page these endpoints replace sat behind `role:Admin`. */
    public function test_the_endpoints_require_an_admin(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);

        $this->getJson('/api/v1/settings/companies')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/companies')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/settings/companies', ['name' => 'Sneaky'])->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/v1/settings/companies/{$company->id}")->assertForbidden();
    }

    public function test_it_lists_companies_with_their_departments_and_totals(): void
    {
        $steel = Company::create(['name' => 'Steel tech', 'is_active' => true]);
        $miknas = Company::create(['name' => 'Miknas Industrial', 'is_active' => false]);
        $steel->departments()->create(['name' => 'Welding', 'is_active' => true]);
        $steel->departments()->create(['name' => 'Assembly', 'is_active' => false]);
        $miknas->departments()->create(['name' => 'Accounts', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/companies')->assertOk();

        // Alphabetical, as the Blade page ordered them.
        $this->assertSame(['Miknas Industrial', 'Steel tech'], array_column($response->json('data'), 'name'));
        $this->assertSame(['Assembly', 'Welding'], array_column($response->json('data.1.departments'), 'name'));
        $this->assertFalse($response->json('data.0.is_active'));
        $this->assertSame(2, $response->json('meta.total_companies'));
        $this->assertSame(3, $response->json('meta.total_departments'));
    }

    public function test_it_creates_updates_and_deactivates_a_company(): void
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/settings/companies', ['name' => 'Miknas Industrial'])
            ->assertCreated();
        $this->assertTrue($created->json('data.is_active'));

        $this->actingAs($admin)
            ->putJson("/api/v1/settings/companies/{$created->json('data.id')}", [
                'name' => 'Miknas Industrial LLC',
                'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('data.name', 'Miknas Industrial LLC')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_company_names_are_unique(): void
    {
        Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/companies', ['name' => 'Miknas Industrial'])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /**
     * settings_projects.company_id is `on delete set null`, so the Blade page's
     * unguarded delete quietly orphaned every project the company owned.
     */
    public function test_a_company_that_still_owns_projects_cannot_be_deleted(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        ProjectSetting::create(['name' => 'New Warehouse', 'company_id' => $company->id, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/settings/companies/{$company->id}")
            ->assertStatus(422);

        $this->assertDatabaseCount('settings_companies', 1);
        $this->assertNotNull(ProjectSetting::first()->company_id);
    }

    public function test_deleting_a_company_takes_its_departments_with_it(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $company->departments()->create(['name' => 'Welding', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/settings/companies/{$company->id}")->assertOk();

        $this->assertDatabaseCount('settings_companies', 0);
        $this->assertDatabaseCount('settings_departments', 0);
    }

    public function test_department_writes_return_the_whole_company(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $admin = $this->admin();

        $created = $this->actingAs($admin)
            ->postJson("/api/v1/settings/companies/{$company->id}/departments", ['name' => 'Welding'])
            ->assertCreated();
        $this->assertSame('Miknas Industrial', $created->json('data.name'));
        $this->assertSame(['Welding'], array_column($created->json('data.departments'), 'name'));

        $departmentId = $created->json('data.departments.0.id');

        $this->actingAs($admin)
            ->putJson("/api/v1/settings/companies/{$company->id}/departments/{$departmentId}", [
                'name' => 'Fabrication', 'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('data.departments.0.name', 'Fabrication')
            ->assertJsonPath('data.departments.0.is_active', false);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/settings/companies/{$company->id}/departments/{$departmentId}")
            ->assertOk()->assertJsonPath('data.departments', []);
    }

    public function test_two_departments_of_one_company_cannot_share_a_name(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $company->departments()->create(['name' => 'Welding', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/settings/companies/{$company->id}/departments", ['name' => 'Welding'])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);

        // The same name under a different company is fine.
        $other = Company::create(['name' => 'Steel tech', 'is_active' => true]);
        $this->actingAs($this->admin())
            ->postJson("/api/v1/settings/companies/{$other->id}/departments", ['name' => 'Welding'])
            ->assertCreated();
    }

    /** Both ids come from the URL, so a mismatched pair must not be writable. */
    public function test_a_department_cannot_be_edited_through_another_company(): void
    {
        $owner = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $other = Company::create(['name' => 'Steel tech', 'is_active' => true]);
        $department = $owner->departments()->create(['name' => 'Welding', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/settings/companies/{$other->id}/departments/{$department->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Welding', Department::find($department->id)->name);
    }

    /** A 1×1 PNG, as the browser sends after scaling. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function test_a_company_takes_a_logo_and_a_stamp_and_shows_them(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson("/api/v1/settings/companies/{$company->id}/images/logo", ['image' => self::PNG])
            ->assertOk()
            ->assertJsonPath('data.logo', self::PNG)
            ->assertJsonPath('data.stamp', null)
            ->assertJsonPath('message', 'Logo saved for Miknas Industrial.');

        $this->actingAs($admin)
            ->putJson("/api/v1/settings/companies/{$company->id}/images/stamp", ['image' => self::PNG])
            ->assertOk()->assertJsonPath('data.stamp', self::PNG);

        $this->actingAs($admin)->getJson('/api/v1/settings/companies')
            ->assertJsonPath('data.0.logo', self::PNG)
            ->assertJsonPath('data.0.stamp', self::PNG);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/settings/companies/{$company->id}/images/logo")
            ->assertOk()
            ->assertJsonPath('data.logo', null)
            ->assertJsonPath('data.stamp', self::PNG)
            ->assertJsonPath('message', 'Logo removed from Miknas Industrial.');
    }

    public function test_only_a_real_png_or_jpeg_is_accepted(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $url = "/api/v1/settings/companies/{$company->id}/images/stamp";

        foreach ([
            'not a data url',
            'data:image/gif;base64,R0lGODlhAQABAAAAACw=',
            'data:image/png;base64,'.base64_encode('<svg onload="alert(1)"/>'),
        ] as $bad) {
            $this->actingAs($this->admin())->putJson($url, ['image' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['image' => 'The Stamp must be a PNG or JPEG image under 512 KB.']);
        }
        $this->assertNull($company->fresh()->stamp_image);
    }

    public function test_only_logo_and_stamp_are_images(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/settings/companies/{$company->id}/images/name", ['image' => self::PNG])
            ->assertNotFound();
    }

    public function test_changing_an_image_needs_companies_edit(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('companies.view');

        $this->actingAs($viewer)
            ->putJson("/api/v1/settings/companies/{$company->id}/images/logo", ['image' => self::PNG])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->deleteJson("/api/v1/settings/companies/{$company->id}/images/logo")
            ->assertForbidden();
    }

    /** Tens of kilobytes each: nothing but the Companies page should carry them. */
    public function test_the_images_stay_out_of_other_payloads(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $company->forceFill(['logo_image' => self::PNG, 'stamp_image' => self::PNG])->save();

        $this->assertArrayNotHasKey('logo_image', $company->fresh()->toArray());
        $this->assertArrayNotHasKey('stamp_image', $company->fresh()->toArray());
    }
}
