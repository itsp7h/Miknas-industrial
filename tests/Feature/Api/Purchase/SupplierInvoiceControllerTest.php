<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\SupplierInvoiceDeleted;
use App\Events\SupplierInvoiceSaved;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupplierInvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::factory()->create(['name' => 'Gulf Metals']);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-001',
            'invoice_date' => now()->toDateString(),
            'subtotal' => 100,
            'vat_amount' => 10,
            'total_amount' => 110,
        ], $overrides);
    }

    private function makeInvoice(array $attributes = []): SupplierInvoice
    {
        return SupplierInvoice::create(array_merge([
            'invoice_number' => 'INV-'.random_int(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'invoice_date' => now(),
            'subtotal' => 100,
            'vat_amount' => 10,
            'total_amount' => 110,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ], $attributes));
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/purchase/invoices')->assertUnauthorized();
    }

    public function test_it_lists_invoices_with_the_supplier_and_outstanding_figure(): void
    {
        $this->makeInvoice(['total_amount' => 110, 'paid_amount' => 40]);

        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.supplier_name', 'Gulf Metals')
            ->assertJsonPath('data.0.outstanding', '70.00');
    }

    public function test_it_creates_an_invoice_as_unpaid_with_nothing_paid(): void
    {
        Event::fake([SupplierInvoiceSaved::class]);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/invoices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.paid_amount', '0.00')
            ->assertJsonPath('data.outstanding', '110.00');

        Event::assertDispatched(SupplierInvoiceSaved::class);
    }

    public function test_it_rejects_a_duplicate_invoice_number(): void
    {
        $this->makeInvoice(['invoice_number' => 'INV-001']);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/invoices', $this->payload(['invoice_number' => 'INV-001']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice_number');
    }

    /** Its own number must not collide with itself on update. */
    public function test_an_invoice_can_keep_its_own_number_when_updated(): void
    {
        $invoice = $this->makeInvoice(['invoice_number' => 'INV-777']);

        $this->actingAs($this->user())
            ->putJson("/api/v1/purchase/invoices/{$invoice->id}", $this->payload(['invoice_number' => 'INV-777']))
            ->assertOk();
    }

    public function test_it_rejects_a_due_date_before_the_invoice_date(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/invoices', $this->payload([
                'due_date' => now()->subDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('due_date');
    }

    // ------------------------------------------------------------------
    // The Blade controller passed $request->all() into create()/update(), and
    // both `paid_amount` and `status` are fillable — so a crafted request could
    // mark an invoice paid without any payment existing.
    // ------------------------------------------------------------------

    public function test_paid_amount_cannot_be_set_through_the_api(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user())
            ->putJson("/api/v1/purchase/invoices/{$invoice->id}", $this->payload([
                'invoice_number' => $invoice->invoice_number,
                'paid_amount' => 999,
            ]))
            ->assertOk();

        $this->assertSame('0.00', (string) $invoice->fresh()->paid_amount);
    }

    public function test_status_cannot_be_set_when_creating_an_invoice(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/invoices', $this->payload(['status' => 'paid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /** The Blade edit form did expose Status, so it stays editable there. */
    public function test_status_may_be_changed_when_editing_an_invoice(): void
    {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user())
            ->putJson("/api/v1/purchase/invoices/{$invoice->id}", $this->payload([
                'invoice_number' => $invoice->invoice_number,
                'status' => 'partial',
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', 'partial');
    }

    // ------------------------------------------------------------------

    public function test_it_deletes_an_invoice_with_no_payments(): void
    {
        Event::fake([SupplierInvoiceDeleted::class]);
        $invoice = $this->makeInvoice();

        $this->actingAs($this->user())
            ->deleteJson("/api/v1/purchase/invoices/{$invoice->id}")
            ->assertOk();

        $this->assertDatabaseMissing('supplier_invoices', ['id' => $invoice->id]);
        Event::assertDispatched(SupplierInvoiceDeleted::class);
    }

    /** Payments hang off the invoice; deleting it would orphan them. */
    public function test_it_refuses_to_delete_an_invoice_that_has_payments(): void
    {
        $invoice = $this->makeInvoice();
        SupplierPayment::create([
            'payment_number' => 'PAY-0001',
            'supplier_invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'payment_date' => now(),
            'amount' => 50,
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($this->user())
            ->deleteJson("/api/v1/purchase/invoices/{$invoice->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('supplier_invoices', ['id' => $invoice->id]);
    }

    public function test_form_options_only_offers_received_orders_and_confirmed_grns(): void
    {
        $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/invoices/form-options')
            ->assertOk()
            ->assertJsonPath('suppliers.0.name', 'Gulf Metals')
            ->assertJsonPath('purchase_orders', [])
            ->assertJsonPath('grns', []);
    }
}
