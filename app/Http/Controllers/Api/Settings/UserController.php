<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => User::with(['roles', 'permissions'])->orderBy('name')->get()
                ->map(fn (User $user) => $this->payload($user))->values(),
            'roles' => Role::orderBy('name')->pluck('name'),
            // Label and name both travel: the page shows the label and posts
            // the name.
            'permissions' => collect(config('purchase_access.permissions'))
                ->map(fn ($label, $name) => ['name' => $name, 'label' => $label])
                ->values(),
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
