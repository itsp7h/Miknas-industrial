<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
            'roles' => ['Requester'],
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
            'roles' => [],
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
            'roles' => ['Requester'],
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
            'roles' => [],
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
            'roles' => ['Requester'],
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_non_admin_cannot_create_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'new@example.test',
        ])->assertForbidden();
    }

    public function test_admin_can_create_a_user_and_a_password_setup_email_is_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'new@example.test',
            'roles' => ['Requester'],
        ]);

        $response->assertCreated();
        $response->assertJson(['message' => 'New Person created. A password-setup email has been sent.']);
        $this->assertDatabaseHas('users', ['email' => 'new@example.test']);

        $newUser = User::where('email', 'new@example.test')->first();
        $this->assertTrue($newUser->hasRole('Requester'));
        $this->assertNull($newUser->email_verified_at);

        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_admin_can_create_a_user_with_a_manual_password_and_no_email_is_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'manual@example.test',
            'roles' => ['Requester'],
            'mode' => 'password',
            'password' => 'CorrectHorseBattery9!',
            'password_confirmation' => 'CorrectHorseBattery9!',
        ]);

        $response->assertCreated();
        $response->assertJson(['message' => 'New Person created.']);

        $newUser = User::where('email', 'manual@example.test')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue(Hash::check('CorrectHorseBattery9!', $newUser->password));
        $this->assertNotNull($newUser->email_verified_at);
        $this->assertTrue($newUser->hasRole('Requester'));

        Notification::assertNothingSent();
    }

    public function test_creating_a_user_with_an_invalid_mode_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'badmode@example.test',
            'mode' => 'foo',
        ])->assertStatus(422)->assertJsonValidationErrors('mode');
    }

    public function test_creating_a_user_with_a_short_manual_password_fails_validation(): void
    {
        // Only length is enforced today: Rules\Password::defaults() has no app-level
        // customization (checked app/Providers/*), so it degrades to Laravel's built-in min(8).
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'shortpass@example.test',
            'mode' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_creating_a_user_with_manual_mode_and_mismatched_passwords_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'mismatch@example.test',
            'mode' => 'password',
            'password' => 'CorrectHorseBattery9!',
            'password_confirmation' => 'DifferentPassword9!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_creating_a_user_with_manual_mode_and_no_password_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'nopassword@example.test',
            'mode' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_creating_a_user_with_a_duplicate_email_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'taken@example.test',
        ])->assertStatus(422);
    }

    public function test_creating_a_user_with_an_uppercase_email_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson(route('settings.users.store'), [
            'name' => 'New Person',
            'email' => 'Mixed@Example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }
}
