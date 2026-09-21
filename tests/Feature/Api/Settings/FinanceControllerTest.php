<?php

namespace Tests\Feature\Api\Settings;

use App\Http\Controllers\Api\Settings\FinanceController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceControllerTest extends TestCase
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
        $this->getJson('/api/v1/settings/finance')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/finance')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->putJson('/api/v1/settings/finance', ['vat_rate' => 99])->assertForbidden();

        $this->assertNull(Setting::get('vat_rate'));
    }

    public function test_it_reads_and_writes_the_rate_as_a_number(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/finance')->assertOk()->assertJsonPath('vat_rate', 0);

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/finance', ['vat_rate' => 10.5])
            ->assertOk()->assertJsonPath('vat_rate', 10.5);

        // Stored as a string, which is what the sales invoice controller reads.
        $this->assertSame('10.5', Setting::get('vat_rate'));
        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/finance')->assertOk()->assertJsonPath('vat_rate', 10.5);
    }

    public function test_the_rate_must_be_a_percentage(): void
    {
        Setting::set('vat_rate', '10');
        $admin = $this->admin();

        foreach ([-1, 101, 'abc', null] as $bad) {
            $this->actingAs($admin)
                ->putJson('/api/v1/settings/finance', ['vat_rate' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors('vat_rate');
        }

        $this->assertSame('10', Setting::get('vat_rate'));
    }

    /**
     * Bahrain, without anything stored. The app is used there, so the setting
     * is right before anyone opens the page.
     */
    public function test_the_currency_defaults_to_bahrain(): void
    {
        $this->assertNull(Setting::get('currency_code'));

        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/finance')->assertOk()
            ->assertJsonPath('currency_code', 'BHD')
            // Written BD, not with its ISO code.
            ->assertJsonPath('currency_symbol', 'BD');
    }

    public function test_it_saves_the_currency_and_rejects_an_unknown_one(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/finance', ['currency_code' => 'USD'])
            ->assertOk()
            ->assertJsonPath('currency_code', 'USD')
            ->assertJsonPath('currency_symbol', '$')
            ->assertJsonPath('message', 'Currency saved.');

        $this->assertSame('USD', Setting::get('currency_code'));

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/finance', ['currency_code' => 'XYZ'])
            ->assertStatus(422)->assertJsonValidationErrors('currency_code');

        $this->assertSame('USD', Setting::get('currency_code'));
    }

    /** A code that is no longer offered must not leave the app with no symbol. */
    public function test_an_unrecognised_stored_code_falls_back_to_bahrain(): void
    {
        Setting::set('currency_code', 'ZZZ');

        $this->assertSame('BHD', FinanceController::currencyCode());
        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/finance')->assertOk()
            ->assertJsonPath('currency_code', 'BHD');
    }

    /**
     * Each card saves on its own. Saving one must not reset the other to a
     * default — a half-typed rate would be silently written otherwise.
     */
    public function test_saving_one_setting_leaves_the_other_alone(): void
    {
        Setting::set('vat_rate', '10');
        Setting::set('currency_code', 'USD');

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/finance', ['currency_code' => 'BHD'])->assertOk();
        $this->assertSame('10', Setting::get('vat_rate'));

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/finance', ['vat_rate' => 5])->assertOk();
        $this->assertSame('BHD', Setting::get('currency_code'));
    }

    /** Zero is the documented way to turn VAT off, so it must not be rejected. */
    public function test_zero_is_accepted(): void
    {
        Setting::set('vat_rate', '10');

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/finance', ['vat_rate' => 0])
            ->assertOk()->assertJsonPath('vat_rate', 0);

        $this->assertSame('0', Setting::get('vat_rate'));
    }
}
