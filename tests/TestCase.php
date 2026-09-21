<?php

namespace Tests;

use App\Models\MailAccount;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

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

    /**
     * An enabled mail account plus a faked mailer, for tests that send.
     *
     * RfqInvitationService refuses to send when no account is configured — and
     * now says so rather than swallowing it — so a test that wants a successful
     * send has to set one up, the way a real installation does.
     */
    protected function workingMailAccount(): MailAccount
    {
        Mail::fake();

        return MailAccount::create([
            'name' => 'test-mailer',
            'label' => 'Test mailer',
            'type' => 'smtp',
            'from_address' => 'erp@example.test',
            'from_name' => 'SteelERP',
            'config' => ['host' => '127.0.0.1', 'port' => 1025, 'encryption' => 'none'],
            'enabled' => true,
        ]);
    }

    protected function seedRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        // The profiles in config/purchase_access.php are the roles — Admin,
        // Operation Manager, GM, Finance. The five that used to be seeded here
        // carried no permissions and were checked nowhere but Admin.
        if (Schema::hasTable('permissions')) {
            (new AccessSeeder)->run();
        }
    }
}
