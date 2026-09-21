<?php

namespace Tests\Feature\Api\Settings;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Settings\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function numbers(): DocumentNumberService
    {
        return app(DocumentNumberService::class);
    }

    /** An order already in the series, so the next one has to follow it. */
    private function issued(string $number): void
    {
        PurchaseOrder::create([
            'po_number' => $number,
            'supplier_id' => Supplier::factory()->create()->id,
            'po_date' => '2026-05-04',
            'total_amount' => 0,
            'status' => 'draft',
        ]);
    }

    public function test_a_number_reads_code_document_year_and_sequence(): void
    {
        $company = Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);

        Carbon::setTestNow('2026-05-04');
        $this->assertSame('ST-LPO-26-0001', $this->numbers()->next($company));
    }

    public function test_the_sequence_runs_per_company(): void
    {
        $steel = Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);
        $miknas = Company::create(['name' => 'Miknas Industrial', 'lpo_code' => 'MI', 'is_active' => true]);

        Carbon::setTestNow('2026-05-04');
        $this->issued('ST-LPO-26-0001');
        $this->issued('ST-LPO-26-0002');

        // Steel Tech has issued two; Miknas Industrial has issued none.
        $this->assertSame('ST-LPO-26-0003', $this->numbers()->next($steel));
        $this->assertSame('MI-LPO-26-0001', $this->numbers()->next($miknas));
    }

    public function test_the_sequence_starts_again_each_year(): void
    {
        $company = Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);
        $this->issued('ST-LPO-26-0009');

        Carbon::setTestNow('2026-12-31');
        $this->assertSame('ST-LPO-26-0010', $this->numbers()->next($company));

        Carbon::setTestNow('2027-01-01');
        $this->assertSame('ST-LPO-27-0001', $this->numbers()->next($company));
    }

    /** An order raised without a purchase request has no company behind it. */
    public function test_an_order_with_no_company_falls_to_the_house_series(): void
    {
        Carbon::setTestNow('2026-05-04');

        $this->assertSame('LPO-26-0001', $this->numbers()->next(null));
        $this->assertSame('LPO-26-0001', $this->numbers()->next(
            Company::create(['name' => 'Unset', 'lpo_code' => null, 'is_active' => true])
        ));
    }

    public function test_it_passes_four_digits(): void
    {
        $company = Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);
        $this->issued('ST-LPO-26-9999');

        Carbon::setTestNow('2026-05-04');
        // Padding is a minimum, not a ceiling: the series keeps counting.
        $this->assertSame('ST-LPO-26-10000', $this->numbers()->next($company));
    }

    /** One code, two series: an MPR and an LPO differ only in the middle. */
    public function test_mpr_numbers_use_the_same_company_code(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'lpo_code' => 'MI', 'is_active' => true]);

        Carbon::setTestNow('2026-05-04');
        $this->assertSame('MI-MPR-26-0001', $this->numbers()->next($company, DocumentNumberService::MPR));
        $this->assertSame('MI-LPO-26-0001', $this->numbers()->next($company, DocumentNumberService::LPO));
    }

    /** The two documents count separately: issuing an LPO does not move the MPRs on. */
    public function test_each_document_keeps_its_own_sequence(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'lpo_code' => 'MI', 'is_active' => true]);
        Carbon::setTestNow('2026-05-04');

        $this->issued('MI-LPO-26-0001');
        $this->issued('MI-LPO-26-0002');
        PurchaseRequest::factory()->create(['request_number' => 'MI-MPR-26-0001', 'company_name' => 'Miknas Industrial']);

        $this->assertSame('MI-LPO-26-0003', $this->numbers()->next($company, DocumentNumberService::LPO));
        $this->assertSame('MI-MPR-26-0002', $this->numbers()->next($company, DocumentNumberService::MPR));
    }

    public function test_the_page_carries_the_next_number_for_both_documents(): void
    {
        Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);
        Carbon::setTestNow('2026-05-04');

        $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/document-numbering')
            ->assertOk()
            ->assertJsonPath('data.0.next_number', 'ST-LPO-26-0001')
            ->assertJsonPath('data.0.next_mpr_number', 'ST-MPR-26-0001');
    }

    public function test_the_page_lists_each_company_with_its_next_number(): void
    {
        Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);
        Carbon::setTestNow('2026-05-04');

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/document-numbering')
            ->assertOk();

        $response->assertJsonPath('data.0.name', 'Steel Tech');
        $response->assertJsonPath('data.0.lpo_code', 'ST');
        $response->assertJsonPath('data.0.next_number', 'ST-LPO-26-0001');
    }

    public function test_admin_can_change_a_code(): void
    {
        $company = Company::create(['name' => 'Matana Steel Factory', 'lpo_code' => 'MSF', 'is_active' => true]);
        Carbon::setTestNow('2026-05-04');

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/document-numbering', [
                'codes' => [['id' => $company->id, 'lpo_code' => 'ms']],
            ])
            ->assertOk()
            // Stored upper-cased, whatever was typed.
            ->assertJsonPath('data.0.lpo_code', 'MS')
            ->assertJsonPath('data.0.next_number', 'MS-LPO-26-0001');

        $this->assertSame('MS', $company->fresh()->lpo_code);
    }

    public function test_two_companies_cannot_share_a_code(): void
    {
        $one = Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);
        $two = Company::create(['name' => 'Miknas Industrial', 'lpo_code' => 'MI', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/document-numbering', [
                'codes' => [
                    ['id' => $one->id, 'lpo_code' => 'ST'],
                    ['id' => $two->id, 'lpo_code' => 'st'],
                ],
            ])
            ->assertStatus(422);

        // Sharing a code would share a sequence, so neither is written.
        $this->assertSame('MI', $two->fresh()->lpo_code);
    }

    public function test_a_code_may_not_contain_punctuation(): void
    {
        $company = Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);

        // A dash would make the sequence unparseable and restart the count.
        $this->actingAs($this->admin())
            ->putJson('/api/v1/settings/document-numbering', [
                'codes' => [['id' => $company->id, 'lpo_code' => 'ST-X']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('codes.0.lpo_code');
    }

    public function test_the_endpoints_are_behind_the_settings_permissions(): void
    {
        $company = Company::create(['name' => 'Steel Tech', 'lpo_code' => 'ST', 'is_active' => true]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson('/api/v1/settings/document-numbering')->assertForbidden();
        $this->actingAs($outsider)
            ->putJson('/api/v1/settings/document-numbering', ['codes' => [['id' => $company->id, 'lpo_code' => 'XX']]])
            ->assertForbidden();

        // Viewing is not editing.
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('settings.view');
        $this->actingAs($viewer)->getJson('/api/v1/settings/document-numbering')->assertOk();
        $this->actingAs($viewer)
            ->putJson('/api/v1/settings/document-numbering', ['codes' => [['id' => $company->id, 'lpo_code' => 'XX']]])
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
