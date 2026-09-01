<?php

namespace Tests\Feature\Api\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_the_endpoints_require_an_admin(): void
    {
        $this->getJson('/api/v1/settings/integrations/whatsapp')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/integrations/whatsapp')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->putJson('/api/v1/settings/integrations/whatsapp', [])->assertForbidden();
    }

    /**
     * The Blade page rendered the live UltraMSG token into an input's `value`,
     * so it reached the browser on every visit. The endpoint reports only that
     * one is stored.
     */
    public function test_it_never_sends_the_token_or_webhook_secret_to_the_browser(): void
    {
        Setting::set('ultramsg_enabled', '1');
        Setting::set('ultramsg_instance_id', 'instance177593');
        Setting::set('ultramsg_token', 'super-secret-token');
        Setting::set('ultramsg_webhook_secret', 'hmac-secret');

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/integrations/whatsapp')->assertOk();

        $response->assertJsonMissing(['token' => 'super-secret-token']);
        $this->assertStringNotContainsString('super-secret-token', $response->getContent());
        $this->assertStringNotContainsString('hmac-secret', $response->getContent());

        $this->assertTrue($response->json('enabled'));
        $this->assertTrue($response->json('token_set'));
        $this->assertTrue($response->json('webhook_secret_set'));
        $this->assertSame('instance177593', $response->json('instance_id'));
    }

    public function test_it_saves_the_settings_and_the_toggle(): void
    {
        $this->actingAs($this->admin())->putJson('/api/v1/settings/integrations/whatsapp', [
            'enabled' => true,
            'instance_id' => 'instance1',
            'webhook_path' => 'ultra-message/webhook',
            'token' => 'first-token',
        ])->assertOk()->assertJsonPath('enabled', true);

        $this->assertSame('1', Setting::get('ultramsg_enabled'));
        $this->assertSame('first-token', Setting::get('ultramsg_token'));

        $this->actingAs($this->admin())->putJson('/api/v1/settings/integrations/whatsapp', [
            'enabled' => false,
            'instance_id' => 'instance1',
            'webhook_path' => 'ultra-message/webhook',
        ])->assertOk()->assertJsonPath('enabled', false);

        $this->assertSame('0', Setting::get('ultramsg_enabled'));
    }

    /** A blank token means "leave it alone", not "wipe it". */
    public function test_saving_without_a_token_keeps_the_stored_one(): void
    {
        Setting::set('ultramsg_token', 'stored-token');

        $this->actingAs($this->admin())->putJson('/api/v1/settings/integrations/whatsapp', [
            'instance_id' => 'instance1',
            'webhook_path' => 'ultra-message/webhook',
            'token' => '',
        ])->assertOk();

        $this->assertSame('stored-token', Setting::get('ultramsg_token'));
    }

    public function test_a_token_is_required_the_first_time(): void
    {
        $this->actingAs($this->admin())->putJson('/api/v1/settings/integrations/whatsapp', [
            'instance_id' => 'instance1',
            'webhook_path' => 'ultra-message/webhook',
        ])->assertStatus(422)->assertJsonValidationErrors('token');

        $this->assertSame('', Setting::get('ultramsg_instance_id', ''));
    }

    /** The secret is optional, so there has to be a way to remove one. */
    public function test_the_webhook_secret_can_be_cleared_explicitly(): void
    {
        Setting::set('ultramsg_token', 'stored-token');
        Setting::set('ultramsg_webhook_secret', 'hmac-secret');

        $this->actingAs($this->admin())->putJson('/api/v1/settings/integrations/whatsapp', [
            'instance_id' => 'instance1',
            'webhook_path' => 'ultra-message/webhook',
            'clear_webhook_secret' => true,
        ])->assertOk()->assertJsonPath('webhook_secret_set', false);

        $this->assertSame('', Setting::get('ultramsg_webhook_secret'));
    }

    public function test_the_test_message_endpoint_validates_its_input(): void
    {
        // No recipient and no body: nothing should be attempted.
        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/integrations/whatsapp/test-message', [])
            ->assertStatus(422)->assertJsonValidationErrors(['to', 'body']);
    }
}
