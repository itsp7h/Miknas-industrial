<?php

namespace Tests\Feature\Api\Settings;

use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    private function smtpAccount(): MailAccount
    {
        return MailAccount::create([
            'name' => 'support', 'label' => 'Customer Support', 'type' => 'smtp',
            'from_address' => 'noreply@example.test', 'from_name' => 'SteelERP', 'enabled' => true,
            'config' => [
                'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
                'username' => 'user@example.test', 'password' => 'smtp-secret',
            ],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'support', 'label' => 'Customer Support', 'type' => 'smtp',
            'from_address' => 'noreply@example.test', 'from_name' => 'SteelERP',
            'config' => [
                'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
                'username' => 'user@example.test', 'password' => 'smtp-secret',
            ],
        ], $overrides);
    }

    public function test_the_endpoints_require_an_admin(): void
    {
        $account = $this->smtpAccount();

        $this->getJson('/api/v1/settings/mail-accounts')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/mail-accounts')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/settings/mail-accounts/{$account->id}")->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/v1/settings/mail-accounts/{$account->id}")->assertForbidden();
    }

    /**
     * The Blade page's show endpoint returned the whole config, so opening the
     * edit form shipped the SMTP password (or Azure client secret) to the
     * browser. Neither leaves the server now.
     */
    public function test_it_never_sends_a_stored_secret_to_the_browser(): void
    {
        $account = $this->smtpAccount();

        $response = $this->actingAs($this->admin())
            ->getJson("/api/v1/settings/mail-accounts/{$account->id}")->assertOk();

        $this->assertStringNotContainsString('smtp-secret', $response->getContent());
        // Everything else the form needs is still there.
        $this->assertSame('smtp.example.test', $response->json('data.config.host'));
        $this->assertSame('user@example.test', $response->json('data.config.username'));
        $this->assertTrue($response->json('data.secrets_set.password'));
    }

    public function test_it_creates_and_lists_accounts(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/mail-accounts', $this->payload())
            ->assertCreated()->assertJsonPath('data.label', 'Customer Support');

        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/mail-accounts')->assertOk()
            ->assertJsonPath('data.0.name', 'support');
    }

    public function test_the_account_name_must_be_a_slug_and_unique(): void
    {
        $this->smtpAccount();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/mail-accounts', $this->payload(['name' => 'Customer Support']))
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson('/api/v1/settings/mail-accounts', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    /** A blank secret on edit means "unchanged", so it must not be wiped. */
    public function test_updating_without_a_password_keeps_the_stored_one(): void
    {
        $account = $this->smtpAccount();

        $this->actingAs($this->admin())
            ->putJson("/api/v1/settings/mail-accounts/{$account->id}", $this->payload([
                'label' => 'Support (renamed)',
                'config' => [
                    'host' => 'smtp.example.test', 'port' => 2525, 'encryption' => 'ssl',
                    'username' => 'user@example.test', 'password' => '',
                ],
            ]))->assertOk()->assertJsonPath('data.label', 'Support (renamed)');

        $fresh = $account->fresh();
        $this->assertSame('smtp-secret', $fresh->config['password']);
        $this->assertSame(2525, $fresh->config['port']);
        $this->assertSame('ssl', $fresh->config['encryption']);
    }

    public function test_an_azure_account_requires_its_secret_on_create_but_not_on_edit(): void
    {
        $admin = $this->admin();
        $azurePayload = [
            'name' => 'reports', 'label' => 'Reports', 'type' => 'azure',
            'from_address' => 'reports@example.test',
            'config' => ['tenant_id' => 'tenant', 'client_id' => 'client', 'client_secret' => ''],
        ];

        $this->actingAs($admin)->postJson('/api/v1/settings/mail-accounts', $azurePayload)
            ->assertStatus(422)->assertJsonValidationErrors('config.client_secret');

        $azurePayload['config']['client_secret'] = 'azure-secret';
        $id = $this->actingAs($admin)->postJson('/api/v1/settings/mail-accounts', $azurePayload)
            ->assertCreated()->json('data.id');

        $azurePayload['config']['client_secret'] = '';
        $azurePayload['label'] = 'Reports (renamed)';
        $this->actingAs($admin)->putJson("/api/v1/settings/mail-accounts/{$id}", $azurePayload)->assertOk();

        $this->assertSame('azure-secret', MailAccount::find($id)->config['client_secret']);
    }

    public function test_it_toggles_and_deletes_an_account(): void
    {
        $account = $this->smtpAccount();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/v1/settings/mail-accounts/{$account->id}/toggle")
            ->assertOk()->assertJsonPath('data.enabled', false);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/settings/mail-accounts/{$account->id}")->assertOk();

        $this->assertDatabaseCount('mail_accounts', 0);
    }

    public function test_the_send_test_endpoint_requires_a_valid_recipient(): void
    {
        $account = $this->smtpAccount();

        $this->actingAs($this->admin())
            ->postJson("/api/v1/settings/mail-accounts/{$account->id}/send-test", ['to' => 'not-an-email'])
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }
}
