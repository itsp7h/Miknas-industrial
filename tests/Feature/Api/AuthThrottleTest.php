<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The React login posts to /api/v1/login rather than Breeze's POST /login, so
 * the protections that used to come free with the Blade form have to hold on
 * the API route too. Before it shared Breeze's LoginRequest, this endpoint
 * validated by hand and had neither of them: unlimited password attempts and
 * no remember-me.
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    public function test_it_locks_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Too many login attempts',
            $response->json('errors.email.0')
        );
    }

    public function test_the_lockout_holds_even_once_the_password_is_correct(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // The point of the limiter: guessing is not rewarded the moment the
        // guess is right.
        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Too many login attempts',
            $response->json('errors.email.0')
        );
        $this->assertGuest('web');
    }

    public function test_a_successful_login_clears_the_attempt_counter(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $key = Str::transliterate(Str::lower($user->email).'|127.0.0.1');

        foreach (range(1, 3) as $ignored) {
            $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertSame(3, RateLimiter::attempts($key));

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        // Asserted on the limiter rather than by logging in again: a second
        // login in the same test process hits Sanctum's RequestGuard, which
        // caches its resolved user for the guard instance's lifetime and has
        // no attempt() — a harness artifact of reusing one container across
        // simulated requests, not behaviour a real request exhibits. The
        // logout test in AuthControllerTest works around the same thing.
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_remember_me_issues_the_remember_cookie(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertOk()->assertCookie($this->recallerName());
    }

    public function test_without_remember_me_no_remember_cookie_is_issued(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()->assertCookieMissing($this->recallerName());
    }

    /**
     * Laravel names the remember cookie after a hash of the guard class, so it
     * is derived here rather than hard-coded.
     */
    private function recallerName(): string
    {
        return auth()->guard('web')->getRecallerName();
    }
}
