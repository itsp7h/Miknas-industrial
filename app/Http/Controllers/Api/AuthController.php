<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
