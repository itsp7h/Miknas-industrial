<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Breeze's LoginRequest is reused rather than re-validating here, so the
     * React login page and the Blade POST /login route share one
     * authentication policy. It carries the two things a hand-rolled
     * Auth::attempt() silently drops: rate limiting (5 attempts per
     * email+IP, then a lockout) and remember-me.
     */
    public function login(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        return $this->userPayload($request);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Send a reset link. Every outcome the broker reports that is not
     * RESET_LINK_SENT — unknown address, asked again inside the throttle
     * window — comes back as a 422 on `email`, which is the shape the React
     * form already renders. Breeze's own POST /forgot-password behaves the
     * same way; this is that flow speaking JSON.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }

    /**
     * Breeze's rules exactly — including Rules\Password::defaults(), so a
     * reset cannot set a password the app would refuse at registration.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }

    /**
     * Re-entering the password for a sensitive area. The caller is told where
     * to go next rather than being redirected: it is a fetch, so a 302 would
     * be followed invisibly and the React page would never learn it had
     * succeeded. `url.intended` is pulled here for the same reason
     * redirect()->intended() pulls it — leaving it set would send the user
     * somewhere stale on their next redirect.
     */
    public function confirmPassword(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->input('password'),
        ])) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'redirect_to' => $request->session()->pull('url.intended', route('dashboard', absolute: false)),
        ]);
    }

    public function me(Request $request)
    {
        return $this->userPayload($request);
    }

    private function userPayload(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'whatsapp_number']),
            'roles' => $user->getRoleNames(),
        ]);
    }
}
