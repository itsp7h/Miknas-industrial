<?php

namespace Tests\Feature\Api\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatControllerTest extends TestCase
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
        $this->getJson('/api/v1/settings/vat')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/vat')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->putJson('/api/v1/settings/vat', ['vat_rate' => 99])->assertForbidden();

        $this->assertNull(Setting::get('vat_rate'));
    }

    public function test_it_reads_and_writes_the_rate_as_a_number(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/vat')->assertOk()->assertJsonPath('vat_rate', 0);

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/vat', ['vat_rate' => 10.5])
            ->assertOk()->assertJsonPath('vat_rate', 10.5);

        // Stored as a string, which is what the sales invoice controller reads.
        $this->assertSame('10.5', Setting::get('vat_rate'));
        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/vat')->assertOk()->assertJsonPath('vat_rate', 10.5);
    }

    public function test_the_rate_must_be_a_percentage(): void
    {
        Setting::set('vat_rate', '10');
        $admin = $this->admin();

        foreach ([-1, 101, 'abc', null] as $bad) {
            $this->actingAs($admin)
                ->putJson('/api/v1/settings/vat', ['vat_rate' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors('vat_rate');
        }

        $this->assertSame('10', Setting::get('vat_rate'));
    }

    /** Zero is the documented way to turn VAT off, so it must not be rejected. */
    public function test_zero_is_accepted(): void
    {
        Setting::set('vat_rate', '10');

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/vat', ['vat_rate' => 0])
            ->assertOk()->assertJsonPath('vat_rate', 0);

        $this->assertSame('0', Setting::get('vat_rate'));
    }
}
