<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'permissions'])->orderBy('name')->get();
        $roles = Role::orderBy('name')->pluck('name');
        $permissions = collect(config('purchase_access.permissions'))
            ->map(fn ($label, $name) => ['name' => $name, 'label' => $label])
            ->values();

        return view('settings.users.index', compact('users', 'roles', 'permissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'roles'   => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Str::random(40),
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => $user->name . ' created. A password-setup email has been sent.',
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->roles->pluck('name'),
                'permissions' => $user->permissions->pluck('name'),
            ],
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles'         => ['array'],
            'roles.*'       => ['string', 'exists:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

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
            'message'     => 'Access updated for ' . $user->name . '.',
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $user->permissions->pluck('name'),
        ]);
    }
}
