<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_the_users_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.users.index'))->assertForbidden();
    }

    public function test_admin_can_view_the_users_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get(route('settings.users.index'))->assertOk();
    }

    public function test_admin_can_assign_a_profile_to_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson(route('settings.users.update', $target), [
            'roles'       => ['Requester'],
            'permissions' => [],
        ]);

        $response->assertOk();
        $this->assertTrue($target->fresh()->hasRole('Requester'));
    }

    public function test_admin_can_toggle_an_individual_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $this->actingAs($admin)->patchJson(route('settings.users.update', $target), [
            'roles'       => [],
            'permissions' => ['purchase-requests.view-all'],
        ]);

        $this->assertTrue($target->fresh()->hasPermissionTo('purchase-requests.view-all'));
    }

    public function test_changing_profile_does_not_clear_existing_custom_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();
        $target->givePermissionTo('purchase-requests.view-all');

        $this->actingAs($admin)->patchJson(route('settings.users.update', $target), [
            'roles'       => ['Requester'],
            'permissions' => ['purchase-requests.view-all'],
        ]);

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('Requester'));
        $this->assertTrue($fresh->hasPermissionTo('purchase-requests.view-all'));
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->patchJson(route('settings.users.update', $admin), [
            'roles'       => [],
            'permissions' => [],
        ]);

        $response->assertStatus(403);
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_non_admin_cannot_update_roles(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->patchJson(route('settings.users.update', $target), [
            'roles'       => ['Requester'],
            'permissions' => [],
        ])->assertForbidden();
    }
}
