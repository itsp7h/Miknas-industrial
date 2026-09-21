<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Profiles become templates rather than containers.
 *
 * A role that grants permissions of its own puts access on a person that the
 * Users page cannot take away: unticking a square there syncs their *direct*
 * permissions, while the role keeps handing the same permission straight back.
 * A GM cut back to "pipeline, approve only" still saw all of Purchase and all
 * of Inventory, and nothing on the form explained why.
 *
 * So: every user keeps exactly the access they have today, held directly, and
 * the roles are emptied. From here a profile seeds someone's squares when it is
 * chosen, and the squares are then the whole truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Nobody loses anything: what a role was granting becomes theirs.
        foreach (User::with(['roles.permissions', 'permissions'])->get() as $user) {
            $effective = $user->getAllPermissions()->pluck('name')->all();

            if ($effective !== []) {
                $user->syncPermissions($effective);
            }
        }

        foreach (Role::with('permissions')->get() as $role) {
            $role->syncPermissions([]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        // Hand the config profiles back to the roles. Direct grants stay put —
        // they are now the record of what each person actually holds.
        foreach (config('access.profiles', []) as $name => $definition) {
            Role::where('name', $name)->first()?->syncPermissions($definition['permissions'] ?? []);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
