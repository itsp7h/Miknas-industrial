<?php

namespace Tests\Feature\Api\Settings;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ported from the deleted Blade UserManagementController: same behaviour, same
 * guards, now against /api/v1/settings/users.
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_endpoints_require_an_admin(): void
    {
        $this->getJson('/api/v1/settings/users')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/users')->assertForbidden();
    }

    public function test_it_lists_users_with_their_roles_permissions_and_the_pickers(): void
    {
        $admin = User::factory()->create(['name' => 'Zoe Admin']);
        $admin->assignRole('Admin');
        $operationManager = User::factory()->create(['name' => 'Alan Requester']);
        $operationManager->assignRole('Operation Manager');
        $operationManager->givePermissionTo('pipeline.view-all');

        $response = $this->actingAs($admin)->getJson('/api/v1/settings/users')->assertOk();

        // Alphabetical, as the page listed them.
        $this->assertSame(['Alan Requester', 'Zoe Admin'], array_column($response->json('data'), 'name'));
        $this->assertSame(['Operation Manager'], $response->json('data.0.roles'));
        $this->assertSame(['pipeline.view-all'], $response->json('data.0.permissions'));

        // The role checkboxes and the permission toggles are both driven by this.
        // The four profiles travel with their descriptions, in config order.
        $profiles = $response->json('profiles');
        $this->assertSame(
            ['Admin', 'Operation Manager', 'GM', 'Finance'],
            array_column($profiles, 'name')
        );
        $this->assertNotEmpty($profiles[1]['description']);
        // Every tab against the actions it offers, so the form can draw a grid.
        $grid = collect($response->json('grid'))->keyBy('tab');
        $this->assertSame('Pipeline', $grid['pipeline']['label']);
        $this->assertSame(
            ['view', 'create', 'edit', 'delete'],
            array_column($grid['pipeline']['actions'], 'action')
        );
        $this->assertSame('pipeline.delete', $grid['pipeline']['actions'][3]['name']);
        // A ledger is posted, never rewritten.
        $this->assertSame(['view', 'create'], array_column($grid['stock-movements']['actions'], 'action'));
        // Users and Integrations have no square at all.
        $this->assertArrayNotHasKey('users', $grid->all());
        $this->assertSame(['users', 'integrations'], $response->json('admin_only_tabs'));
    }

    public function test_admin_can_assign_a_profile_to_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/v1/settings/users/{$target->id}", [
            'roles' => ['Operation Manager'],
            'permissions' => [],
        ]);

        $response->assertOk();
        $this->assertTrue($target->fresh()->hasRole('Operation Manager'));
    }

    public function test_admin_can_toggle_an_individual_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $this->actingAs($admin)->putJson("/api/v1/settings/users/{$target->id}", [
            'roles' => [],
            'permissions' => ['pipeline.view-all'],
        ]);

        $this->assertTrue($target->fresh()->hasPermissionTo('pipeline.view-all'));
    }

    public function test_changing_profile_does_not_clear_existing_custom_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();
        $target->givePermissionTo('pipeline.view-all');

        $this->actingAs($admin)->putJson("/api/v1/settings/users/{$target->id}", [
            'roles' => ['Operation Manager'],
            'permissions' => ['pipeline.view-all'],
        ]);

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('Operation Manager'));
        $this->assertTrue($fresh->hasPermissionTo('pipeline.view-all'));
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->putJson("/api/v1/settings/users/{$admin->id}", [
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

        $this->actingAs($user)->putJson("/api/v1/settings/users/{$target->id}", [
            'roles' => ['Operation Manager'],
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_non_admin_cannot_create_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/settings/users', [
            'name' => 'New Person',
            'email' => 'new@example.test',
        ])->assertForbidden();
    }

    public function test_admin_can_create_a_user_and_a_password_setup_email_is_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->postJson('/api/v1/settings/users', [
            'name' => 'New Person',
            'email' => 'new@example.test',
            'roles' => ['Operation Manager'],
        ]);

        $response->assertCreated();
        $response->assertJson(['message' => 'New Person created. A password-setup email has been sent.']);
        $this->assertDatabaseHas('users', ['email' => 'new@example.test']);

        $newUser = User::where('email', 'new@example.test')->first();
        $this->assertTrue($newUser->hasRole('Operation Manager'));
        $this->assertNull($newUser->email_verified_at);

        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_admin_can_create_a_user_with_a_manual_password_and_no_email_is_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->postJson('/api/v1/settings/users', [
            'name' => 'New Person',
            'email' => 'manual@example.test',
            'roles' => ['Operation Manager'],
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
        $this->assertTrue($newUser->hasRole('Operation Manager'));

        Notification::assertNothingSent();
    }

    public function test_creating_a_user_with_an_invalid_mode_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
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

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
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

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
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

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
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

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
            'name' => 'New Person',
            'email' => 'taken@example.test',
        ])->assertStatus(422);
    }

    /**
     * `prohibited` rather than ignored: a password sent in email mode is a
     * mistake, and swallowing it would leave the admin believing they had set
     * one when a setup link went out instead.
     */
    public function test_a_password_sent_in_email_mode_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
            'name' => 'New Person',
            'email' => 'confused@example.test',
            'mode' => 'email',
            'password' => 'CorrectHorseBattery9!',
            'password_confirmation' => 'CorrectHorseBattery9!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'confused@example.test']);
    }

    public function test_admin_can_email_an_existing_user_a_password_reset_link(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create(['name' => 'Sara Ali', 'email' => 'sara@example.test']);
        $original = $target->password;

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', ['mode' => 'email'])
            ->assertOk()
            ->assertJson(['message' => 'A password-reset email has been sent to sara@example.test.']);

        Notification::assertSentTo($target, ResetPassword::class);
        // Their current password keeps working until they follow the link.
        $this->assertSame($original, $target->fresh()->password);
    }

    public function test_email_is_the_default_when_no_mode_is_given(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', [])
            ->assertOk();

        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_admin_can_set_an_existing_users_password_directly(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create(['name' => 'Sara Ali']);

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', [
                'mode' => 'password',
                'password' => 'CorrectHorseBattery9!',
                'password_confirmation' => 'CorrectHorseBattery9!',
            ])
            ->assertOk()
            ->assertJson(['message' => 'Password updated for Sara Ali.']);

        $this->assertTrue(Hash::check('CorrectHorseBattery9!', $target->fresh()->password));
        Notification::assertNothingSent();
    }

    public function test_resetting_a_password_rotates_the_remember_token(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create(['remember_token' => 'stale-token']);

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', [
                'mode' => 'password',
                'password' => 'CorrectHorseBattery9!',
                'password_confirmation' => 'CorrectHorseBattery9!',
            ])
            ->assertOk();

        // A reset that left an old "remember me" cookie working is not a reset.
        $this->assertNotSame('stale-token', $target->fresh()->remember_token);
    }

    public function test_a_password_sent_while_resetting_in_email_mode_is_rejected(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', [
                'mode' => 'email',
                'password' => 'CorrectHorseBattery9!',
                'password_confirmation' => 'CorrectHorseBattery9!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        Notification::assertNothingSent();
    }

    public function test_resetting_with_a_short_password_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', [
                'mode' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_resetting_with_mismatched_passwords_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', [
                'mode' => 'password',
                'password' => 'CorrectHorseBattery9!',
                'password_confirmation' => 'SomethingElse9!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_non_admin_cannot_reset_another_users_password(): void
    {
        Notification::fake();

        $target = User::factory()->create();

        $this->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', ['mode' => 'email'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/settings/users/'.$target->id.'/reset-password', ['mode' => 'email'])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_creating_a_user_with_an_uppercase_email_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
            'name' => 'New Person',
            'email' => 'Mixed@Example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }
}
