<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

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
    }
}
