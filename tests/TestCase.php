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

        // Every layout calls @vite(...), which throws unless
        // public/build/manifest.json exists — and that file is a build
        // artifact, gitignored, absent in a fresh checkout. So any test that
        // renders a page failed in CI while passing on a machine that happened
        // to have run `npm run build` at some point. The PHP CI job has no
        // Node in it by design (the JS job builds the bundle and would catch a
        // broken build), so stub Vite out here instead: these tests are
        // asserting on server-rendered HTML, never on asset URLs.
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
            (new PurchaseAccessSeeder())->run();
        }
    }
}
