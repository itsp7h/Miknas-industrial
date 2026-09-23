<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view — a React mount point on the shared auth shell.
     *
     * The redirect target is decided here rather than in the page, because
     * only the server knows where the visitor was heading. `url.intended` is
     * read, not pulled: Breeze's own POST /login still consumes it.
     */
    public function create(Request $request): View
    {
        $intended = $request->session()->get('url.intended', '');

        return view('auth.shell', [
            'page' => 'login',
            'props' => [
                'redirectTo' => url()->previous() !== url()->current() && str_starts_with($intended, url('/'))
                    ? $intended
                    : url('/app'),
                'showDevLogin' => app()->environment('local'),
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
