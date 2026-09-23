<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Importing a spreadsheet and exporting a list become squares of their own.
 *
 * They used to ride on `create` and `view`: anyone who could add one supplier
 * could import a thousand, and anyone who could read the list could take a PDF
 * of it away. Those are different capabilities and an Admin should be able to
 * hand out or take back each one, which is what the new squares are for.
 *
 * Nobody loses anything here. Whoever can do it today keeps being able to do
 * it, held explicitly instead of implied — `import` goes to everyone who has
 * `create`, `export` to everyone who has `view`. From tomorrow an Admin can
 * untick either, and unticking will actually mean something.
 *
 * The permissions are created here rather than waited for: `AccessSeeder` runs
 * *after* migrations on deploy, so at this point they do not exist yet.
 */
return new class extends Migration
{
    /** Which new square each existing one implies. */
    private const GRANTS = [
        'suppliers' => ['import' => 'create', 'export' => 'view'],
        'raw-materials' => ['import' => 'create', 'export' => 'view'],
        'finished-goods' => ['import' => 'create', 'export' => 'view'],
        // Projects have an import and a template, but nothing that writes a PDF.
        'projects' => ['import' => 'create'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $tab => $pairs) {
            foreach (array_keys($pairs) as $action) {
                Permission::firstOrCreate(['name' => "{$tab}.{$action}"]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (User::with('permissions')->get() as $user) {
            $held = $user->permissions->pluck('name')->all();
            $new = [];

            foreach (self::GRANTS as $tab => $pairs) {
                foreach ($pairs as $action => $implies) {
                    if (in_array("{$tab}.{$implies}", $held, true)) {
                        $new[] = "{$tab}.{$action}";
                    }
                }
            }

            if ($new !== []) {
                $user->givePermissionTo($new);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $tab => $pairs) {
            foreach (array_keys($pairs) as $action) {
                Permission::where('name', "{$tab}.{$action}")->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
