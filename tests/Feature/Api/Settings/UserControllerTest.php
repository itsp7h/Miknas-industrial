<?php

namespace Tests\Feature\Api\Settings;

use App\Models\PurchaseRequest;
use App\Models\PurchaseSignature;
use App\Models\User;
use App\Support\AccessCatalog;
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

    /**
     * The point of the whole grid: what an Admin ticks is what the person gets.
     *
     * A GM starts with the whole GM profile. Cut back to two squares, every
     * other one must be gone — the profile stays on them as a label, and the
     * role itself hands back nothing.
     *
     * The starting count comes from the catalogue rather than a number written
     * here: what the profile contains is config's business and changes, while
     * what this test is about — that the selection replaces it entirely — does
     * not.
     */
    public function test_the_selection_is_exactly_what_the_user_ends_up_with(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $gm = User::factory()->create();
        $gm->assignRole('GM');
        $gm->syncPermissions(AccessCatalog::defaultPermissionsFor('GM'));
        $this->assertCount(
            count(AccessCatalog::defaultPermissionsFor('GM')),
            $gm->fresh()->getAllPermissions()
        );

        $this->actingAs($admin)->putJson('/api/v1/settings/users/'.$gm->id, [
            'roles' => ['GM'],
            'permissions' => ['pipeline.view', 'pipeline.approve'],
        ])->assertOk();

        $gm = $gm->fresh();
        $this->assertSame(['GM'], $gm->roles->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            ['pipeline.view', 'pipeline.approve'],
            $gm->getAllPermissions()->pluck('name')->all()
        );
        // The sidebar is built from these, so the tabs go with them.
        $this->assertFalse($gm->can('raw-materials.view'));
        $this->assertFalse($gm->can('suppliers.view'));
        $this->assertTrue($gm->can('pipeline.approve'));
    }

    /** Taking every square away leaves nothing, profile or no profile. */
    public function test_clearing_every_square_leaves_the_user_with_no_access(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $gm = User::factory()->create();
        $gm->assignRole('GM');
        $gm->syncPermissions(AccessCatalog::defaultPermissionsFor('GM'));

        $this->actingAs($admin)->putJson('/api/v1/settings/users/'.$gm->id, [
            'roles' => ['GM'],
            'permissions' => [],
        ])->assertOk();

        $this->assertCount(0, $gm->fresh()->getAllPermissions());
    }

    /** A new user is granted their profile's template, since the role grants nothing. */
    public function test_creating_a_user_lays_down_the_profiles_default_squares(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
            'name' => 'New GM',
            'email' => 'newgm@example.test',
            'roles' => ['GM'],
        ])->assertCreated();

        $created = User::where('email', 'newgm@example.test')->first();
        $this->assertEqualsCanonicalizing(
            AccessCatalog::defaultPermissionsFor('GM'),
            $created->getAllPermissions()->pluck('name')->all()
        );
    }

    /** No profile means no squares, rather than a silent inheritance. */
    public function test_creating_a_user_without_a_profile_grants_nothing(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->postJson('/api/v1/settings/users', [
            'name' => 'No Profile',
            'email' => 'noprofile@example.test',
        ])->assertCreated();

        $created = User::where('email', 'noprofile@example.test')->first();
        $this->assertCount(0, $created->getAllPermissions());
    }

    public function test_admin_can_delete_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $victim = User::factory()->create(['name' => 'Departing Dan']);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/settings/users/'.$victim->id)
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('id', $victim->id)
            ->assertJsonPath('message', 'Departing Dan deleted.');

        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }

    public function test_a_non_admin_cannot_delete_a_user(): void
    {
        $victim = User::factory()->create();

        $this->deleteJson('/api/v1/settings/users/'.$victim->id)->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->deleteJson('/api/v1/settings/users/'.$victim->id)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        // A second Admin, so it is being their own account that refuses this
        // rather than their being the last one.
        User::factory()->create()->assignRole('Admin');

        $this->actingAs($admin)
            ->deleteJson('/api/v1/settings/users/'.$admin->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /** Admin is the only way back into this page, so the last one stays. */
    public function test_the_last_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $other = User::factory()->create();
        $other->assignRole('Admin');

        // Two Admins: one of them may go.
        $this->actingAs($admin)->deleteJson('/api/v1/settings/users/'.$other->id)->assertOk();

        // One Admin left, and nobody can remove them — not even themselves,
        // which the previous test covers, and not another Admin, because there
        // is none.
        $this->assertSame(1, User::role('Admin')->count());

        $second = User::factory()->create();
        $second->assignRole('Admin');
        $this->actingAs($second)
            ->deleteJson('/api/v1/settings/users/'.$admin->id)
            ->assertOk();

        $this->actingAs($second)
            ->deleteJson('/api/v1/settings/users/'.$second->id)
            ->assertForbidden();
    }

    /** `purchase_requests.requested_by` is `restrict`: the MPR keeps the name. */
    public function test_a_user_who_raised_a_request_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $requester = User::factory()->create(['name' => 'Rania Requester']);
        PurchaseRequest::factory()->create(['requested_by' => $requester->id]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/settings/users/'.$requester->id)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Rania Requester cannot be deleted: they raised 1 purchase request. '
                .'Records have to keep naming who raised and signed them.'
            );

        $this->assertDatabaseHas('users', ['id' => $requester->id]);
    }

    /** `purchase_signatures.signed_by` is `restrict` for the same reason. */
    public function test_a_user_who_signed_a_request_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $signer = User::factory()->create(['name' => 'Sami Signer']);
        $request = PurchaseRequest::factory()->create();

        PurchaseSignature::create([
            'purchase_request_id' => $request->id,
            'signed_by' => $signer->id,
            'signature_image' => 'data:image/png;base64,iVBORw0KGgo=',
            'signed_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/settings/users/'.$signer->id)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Sami Signer cannot be deleted: they signed 1 request. '
                .'Records have to keep naming who raised and signed them.'
            );
    }

    /** Both refusals in one sentence when both apply. */
    public function test_the_refusal_names_every_reason_at_once(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $busy = User::factory()->create(['name' => 'Bea Busy']);

        $request = PurchaseRequest::factory()->create(['requested_by' => $busy->id]);
        PurchaseRequest::factory()->create(['requested_by' => $busy->id]);
        PurchaseSignature::create([
            'purchase_request_id' => $request->id,
            'signed_by' => $busy->id,
            'signature_image' => 'data:image/png;base64,iVBORw0KGgo=',
            'signed_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/settings/users/'.$busy->id)
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Bea Busy cannot be deleted: they raised 2 purchase requests and they signed 1 request. '
                .'Records have to keep naming who raised and signed them.'
            );
    }

    /** Everywhere else the column is `set null`: the document loses the name. */
    public function test_a_user_named_only_on_a_set_null_column_can_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $requester = User::factory()->create();
        $approver = User::factory()->create(['name' => 'Gil Approver']);

        $request = PurchaseRequest::factory()->create([
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/settings/users/'.$approver->id)
            ->assertOk();

        $this->assertNull($request->fresh()->approved_by);
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
