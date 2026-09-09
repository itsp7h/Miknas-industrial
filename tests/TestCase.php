<?php

namespace Tests;

use Database\Seeders\PurchaseAccessSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        // The PHP job builds no assets, so public/build/manifest.json does not
        // exist there and every @vite view (the SPA shell, Breeze's auth
        // screens) throws "Vite manifest not found" instead of rendering.
        // These tests assert on routes and view data, not on the bundle — the
        // JS job already gates the build — so the manifest is stubbed out here
        // rather than required. Without this the suite passes locally only
        // because a previous `npm run build` left a manifest behind.
        $this->withoutVite();

        // Sanctum's stateful-request detection (EnsureFrontendRequestsAreStateful)
        // only starts a session for requests carrying a Referer/Origin matching
        // SANCTUM_STATEFUL_DOMAINS — a real browser SPA request always has one,
        // so tests hitting /api/* need it too rather than bypassing the check.
        $this->withHeader('Referer', config('app.url'));
    }

    protected function seedRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roles = ['Admin', 'Accounts', 'Store Manager', 'Production Manager', 'Sales Manager'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        if (Schema::hasTable('permissions')) {
            (new PurchaseAccessSeeder)->run();
        }
    }
}
