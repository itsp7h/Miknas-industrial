<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => User::with(['roles', 'permissions'])->orderBy('name')->get()
                ->map(fn (User $user) => $this->payload($user))->values(),
            // The profiles, in the order config lists them — Admin first, then
            // by how much of the system each one runs.
            'profiles' => AccessCatalog::profiles(),
            // Every tab against the actions it offers. The form draws this as a
            // grid, so an Admin can grant any square to anyone regardless of
            // which profile they hold.
            'grid' => AccessCatalog::grid(),
            'admin_only_tabs' => config('access.admin_only_tabs'),
        ]);
    }

    public function store(Request $request)
    {
        $mode = $request->input('mode') ?: 'email';

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'mode' => ['nullable', Rule::in(['email', 'password'])],
            // `prohibited` rather than `nullable`: in email mode a password in
            // the request is a mistake, and silently ignoring it would leave the
            // admin thinking they had set one.
            'password' => $mode === 'password'
                ? ['required', 'confirmed', Rules\Password::defaults()]
                : ['prohibited'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // An unguessable placeholder, replaced when they follow the setup
            // link. Never a known default.
            'password' => $mode === 'password' ? $validated['password'] : Str::random(40),
        ]);

        if ($mode === 'password') {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->syncRoles($validated['roles'] ?? []);
        // The role grants nothing by itself, so the profile's squares are
        // written onto the person here. Without this a new user would hold a
        // profile and no access at all.
        $user->syncPermissions(AccessCatalog::defaultPermissionsFor($validated['roles'][0] ?? null));

        $message = $user->name.' created.';

        if ($mode === 'email') {
            Password::sendResetLink(['email' => $user->email]);
            $message = $user->name.' created. A password-setup email has been sent.';
        }

        return response()->json([
            'message' => $message,
            'data' => $this->payload($user->load(['roles', 'permissions'])),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Otherwise an admin can lock themselves — and possibly everyone — out
        // of this page.
        if (
            $request->user()->id === $user->id
            && $user->hasRole('Admin')
            && ! in_array('Admin', $validated['roles'] ?? [], true)
        ) {
            return response()->json(['message' => 'You cannot remove your own Admin role.'], 403);
        }

        $user->syncRoles($validated['roles'] ?? []);
        $user->syncPermissions($validated['permissions'] ?? []);

        return response()->json([
            'message' => 'Access updated for '.$user->name.'.',
            'data' => $this->payload($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Reset an existing user's password.
     *
     * The two modes are `store()`'s, for the same reasons: "email" sends the
     * very link Breeze's forgot-password flow sends, so the password is only
     * ever known to its owner, and "password" sets one immediately for someone
     * who cannot receive mail. A password sent in email mode is `prohibited`
     * rather than ignored — an admin who typed one would otherwise leave
     * believing they had set it.
     *
     * Either way the remember-me token is rotated: a reset that left an old
     * "remember me" cookie working would not be a reset at all.
     */
    public function resetPassword(Request $request, User $user)
    {
        $mode = $request->input('mode') ?: 'email';

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['email', 'password'])],
            'password' => $mode === 'password'
                ? ['required', 'confirmed', Rules\Password::defaults()]
                : ['prohibited'],
        ]);

        if ($mode === 'password') {
            // `password` is a hashed cast, so the plain value is hashed on the
            // way in — the same path store() takes.
            $user->forceFill([
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
            ])->save();

            $message = 'Password updated for '.$user->name.'.';
        } else {
            $user->forceFill(['remember_token' => Str::random(60)])->save();

            Password::sendResetLink(['email' => $user->email]);

            $message = 'A password-reset email has been sent to '.$user->email.'.';
        }

        return response()->json([
            'message' => $message,
            'data' => $this->payload($user->load(['roles', 'permissions'])),
        ]);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->permissions->pluck('name'),
        ];
    }
}
