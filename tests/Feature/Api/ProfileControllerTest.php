<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ported from the deleted Blade ProfileTest: the same behaviour, now against
 * /api/v1/profile. Everything here is the signed-in user's own account, so
 * these endpoints are not behind role:Admin.
 */
class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->putJson('/api/v1/profile', ['name' => 'X', 'email' => 'x@example.test'])->assertUnauthorized();
        $this->deleteJson('/api/v1/profile', ['password' => 'password'])->assertUnauthorized();
    }

    public function test_it_returns_the_signed_in_users_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $user->assignRole('Admin');
        User::factory()->create(['name' => 'Someone Else']);

        $response = $this->actingAs($user)->getJson('/api/v1/profile')->assertOk();

        $this->assertSame('Test User', $response->json('data.name'));
        $this->assertSame($user->email, $response->json('data.email'));
        $this->assertTrue($response->json('data.email_verified'));
        $this->assertSame(['Admin'], $response->json('data.roles'));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assertOk()->assertJsonPath('data.email_verified', false);

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        // A new address has not been proven yet.
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ])->assertOk()->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_the_email_must_be_unique_and_lowercase(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($user)->putJson('/api/v1/profile', [
            'name' => 'Test User', 'email' => 'taken@example.test',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->actingAs($user)->putJson('/api/v1/profile', [
            'name' => 'Test User', 'email' => 'Mixed@Example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_password_can_be_changed_with_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'CorrectHorseBattery9!',
            'password_confirmation' => 'CorrectHorseBattery9!',
        ])->assertOk();

        $this->assertTrue(Hash::check('CorrectHorseBattery9!', $user->fresh()->password));
    }

    public function test_the_password_change_needs_the_right_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'CorrectHorseBattery9!',
            'password_confirmation' => 'CorrectHorseBattery9!',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'CorrectHorseBattery9!',
            'password_confirmation' => 'DifferentPassword9!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertOk()
            // The session dies with the account, so the page is told where to go.
            ->assertJsonPath('redirect', '/');

        // actingAs() keeps the test's own guard populated, so assert the session
        // guard the SPA actually uses was logged out — and that the row is gone.
        $this->assertFalse(auth()->guard('web')->check());
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/v1/profile', ['password' => 'wrong-password'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertNotNull($user->fresh());
    }

    public function test_the_verification_email_can_be_resent_only_while_unverified(): void
    {
        Notification::fake();

        $verified = User::factory()->create();
        $this->actingAs($verified)
            ->postJson('/api/v1/profile/verification-notification')->assertOk()
            ->assertJsonPath('message', 'That address is already verified.');
        Notification::assertNothingSent();

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->postJson('/api/v1/profile/verification-notification')->assertOk();
        Notification::assertSentTo($unverified, VerifyEmail::class);
    }
}
