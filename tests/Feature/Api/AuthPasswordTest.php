<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The JSON half of the password flow, which the React auth screens use in
 * place of Breeze's Blade forms.
 *
 * Breeze's own POST routes still exist and tests/Feature/Auth still covers
 * them; these are the same policy answering a fetch, so what is asserted
 * here is that the two cannot drift apart — the same rules, the same broker
 * statuses, the same field an error lands on.
 */
class AuthPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_sent_for_a_known_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /** The broker's refusal lands on `email`, which is where the form shows it. */
    public function test_an_unknown_address_comes_back_as_a_field_error(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@erp.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }

    public function test_the_address_must_look_like_one(): void
    {
        $this->postJson('/api/v1/forgot-password', ['email' => 'not-an-address'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_the_password_can_be_reset_with_the_emailed_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->postJson('/api/v1/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertOk();

            return true;
        });

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_an_invalid_token_is_refused(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/reset-password', [
            'token' => 'not-the-token-we-issued',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertFalse(Hash::check('new-password-123', $user->refresh()->password));
    }

    /** Rules\Password::defaults() applies here as it does everywhere else. */
    public function test_a_reset_cannot_set_a_password_the_app_would_refuse(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->postJson('/api/v1/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])->assertStatus(422)->assertJsonValidationErrors('password');

            return true;
        });
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/reset-password', [
            'token' => 'anything',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'a-different-one',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_confirming_a_password_requires_being_signed_in(): void
    {
        $this->postJson('/api/v1/confirm-password', ['password' => 'password'])
            ->assertUnauthorized();
    }

    /** The caller is told where to go; a 302 would be followed invisibly. */
    public function test_confirming_the_password_records_it_and_names_the_destination(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->withSession(['url.intended' => '/app/settings/users'])
            ->postJson('/api/v1/confirm-password', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('redirect_to', '/app/settings/users');

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_confirming_falls_back_to_the_dashboard_when_nothing_was_intended(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->postJson('/api/v1/confirm-password', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('redirect_to', '/dashboard');
    }

    public function test_a_wrong_password_is_refused_and_confirms_nothing(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->postJson('/api/v1/confirm-password', ['password' => 'not-it'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertNull(session('auth.password_confirmed_at'));
    }
}
