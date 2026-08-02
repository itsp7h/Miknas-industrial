<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\SupplierDeleted;
use App\Events\SupplierSaved;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    public function test_index_returns_all_suppliers(): void
    {
        $this->actingUser();
        Supplier::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/purchase/suppliers');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_store_creates_a_supplier_and_broadcasts(): void
    {
        Event::fake([SupplierSaved::class]);
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers', [
            'name' => 'Acme Steel',
            'credit_days' => 30,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('suppliers', ['name' => 'Acme Steel']);
        Event::assertDispatched(SupplierSaved::class);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_modifies_a_supplier_and_broadcasts(): void
    {
        Event::fake([SupplierSaved::class]);
        $this->actingUser();
        $supplier = Supplier::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/purchase/suppliers/{$supplier->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
        Event::assertDispatched(SupplierSaved::class);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/purchase/suppliers');

        $response->assertUnauthorized();
    }

    public function test_store_accepts_tax_number_and_is_active(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers', [
            'name' => 'Acme Steel',
            'tax_number' => 'TRN-12345',
            'is_active' => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.tax_number', 'TRN-12345');
        $response->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('suppliers', ['name' => 'Acme Steel', 'tax_number' => 'TRN-12345', 'is_active' => false]);
    }

    public function test_update_modifies_tax_number_and_is_active(): void
    {
        $this->actingUser();
        $supplier = Supplier::factory()->create(['tax_number' => 'OLD-1', 'is_active' => true]);

        $response = $this->putJson("/api/v1/purchase/suppliers/{$supplier->id}", [
            'name' => $supplier->name,
            'tax_number' => 'NEW-2',
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tax_number', 'NEW-2');
        $response->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'tax_number' => 'NEW-2', 'is_active' => false]);
    }

    public function test_destroy_deletes_a_supplier_and_broadcasts(): void
    {
        Event::fake([SupplierDeleted::class]);
        $this->actingUser();
        $supplier = Supplier::factory()->create();

        $response = $this->deleteJson("/api/v1/purchase/suppliers/{$supplier->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        Event::assertDispatched(SupplierDeleted::class, fn ($e) => $e->supplierId === $supplier->id);
    }

    public function test_destroy_blocks_deletion_of_a_supplier_with_related_purchase_history(): void
    {
        $this->actingUser();
        $supplier = Supplier::factory()->create();
        \App\Models\RfqInvitation::factory()->create(['supplier_id' => $supplier->id]);

        $response = $this->deleteJson("/api/v1/purchase/suppliers/{$supplier->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot delete a supplier that has purchase orders, invoices, or payments.');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_import_rejects_a_non_excel_file(): void
    {
        $this->actingUser();

        $response = $this->postJson('/api/v1/purchase/suppliers/import', [
            'file' => UploadedFile::fake()->create('not-excel.txt', 10),
        ]);

        $response->assertStatus(422);
    }

    public function test_import_processes_a_valid_template_file(): void
    {
        $this->actingUser();

        $path = storage_path('app/test_suppliers_template.xlsx');
        \Illuminate\Support\Facades\Artisan::call('suppliers:template', ['--output' => $path]);
        $this->assertFileExists($path);

        $file = new UploadedFile($path, 'suppliers.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->postJson('/api/v1/purchase/suppliers/import', [
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['imported', 'updated', 'skipped']);
        // The generated template ships with three example supplier rows.
        $response->assertJsonPath('imported', 3);

        @unlink($path);
    }

    public function test_download_template_returns_a_file(): void
    {
        $this->actingUser();

        $response = $this->get('/api/v1/purchase/suppliers/template');

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_export_pdf_returns_a_pdf(): void
    {
        $this->actingUser();
        Supplier::factory()->count(2)->create();

        $response = $this->get('/api/v1/purchase/suppliers/export-pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }
}
