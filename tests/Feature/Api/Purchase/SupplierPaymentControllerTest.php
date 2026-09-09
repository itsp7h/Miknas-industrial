<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\SupplierPaymentDeleted;
use App\Events\SupplierPaymentRecorded;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupplierPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private SupplierInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create(['name' => 'Gulf Metals']);
        $this->invoice = SupplierInvoice::create([
            'invoice_number' => 'INV-001',
            'supplier_id' => $this->supplier->id,
            'invoice_date' => now(),
            'subtotal' => 1000,
            'vat_amount' => 0,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier_invoice_id' => $this->invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => 400,
            'payment_method' => 'bank_transfer',
        ], $overrides);
    }

    private function record(float $amount): SupplierPayment
    {
        $payment = SupplierPayment::create([
            'supplier_invoice_id' => $this->invoice->id,
            'supplier_id' => $this->supplier->id,
            'payment_date' => now(),
            'amount' => $amount,
            'payment_method' => 'cash',
        ]);
        $this->invoice->update([
            'paid_amount' => $this->invoice->payments()->sum('amount'),
            'status' => 'partial',
        ]);

        return $payment;
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/purchase/payments')->assertUnauthorized();
    }

    /**
     * The Blade controller built the payment from $request->all() and never set
     * supplier_id, which is NOT NULL — so every attempt to record a payment died
     * on the constraint and the flow was unreachable. It comes from the invoice.
     */
    public function test_it_records_a_payment_and_derives_the_supplier_from_the_invoice(): void
    {
        Event::fake([SupplierPaymentRecorded::class]);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/payments', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.supplier_id', $this->supplier->id)
            ->assertJsonPath('data.invoice_number', 'INV-001');

        Event::assertDispatched(SupplierPaymentRecorded::class);
    }

    public function test_recording_a_partial_payment_moves_the_invoice_to_partial(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/payments', $this->payload(['amount' => 400]))
            ->assertCreated();

        $this->invoice->refresh();
        $this->assertSame('400.00', (string) $this->invoice->paid_amount);
        $this->assertSame('partial', $this->invoice->status);
    }

    public function test_settling_the_balance_marks_the_invoice_paid(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/payments', $this->payload(['amount' => 1000]))
            ->assertCreated();

        $this->invoice->refresh();
        $this->assertSame('1000.00', (string) $this->invoice->paid_amount);
        $this->assertSame('paid', $this->invoice->status);
    }

    public function test_it_refuses_a_payment_larger_than_the_outstanding_balance(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/payments', $this->payload(['amount' => 1500]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame('0.00', (string) $this->invoice->fresh()->paid_amount);
    }

    /** payment_method is a DB enum; the Blade rule was only string|max:255. */
    public function test_it_rejects_a_payment_method_outside_the_enum(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/payments', $this->payload(['payment_method' => 'bitcoin']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');
    }

    // ------------------------------------------------------------------
    // The Blade controller updated/deleted the payment and stopped there, so
    // the invoice's paid figure drifted out of sync.
    // ------------------------------------------------------------------

    public function test_editing_an_amount_resyncs_the_invoice(): void
    {
        $payment = $this->record(400);

        $this->actingAs($this->user())
            ->putJson("/api/v1/purchase/payments/{$payment->id}", [
                'payment_date' => now()->toDateString(),
                'amount' => 250,
                'payment_method' => 'cash',
            ])
            ->assertOk();

        $this->invoice->refresh();
        $this->assertSame('250.00', (string) $this->invoice->paid_amount);
        $this->assertSame('partial', $this->invoice->status);
    }

    public function test_deleting_a_payment_restores_the_outstanding_balance(): void
    {
        Event::fake([SupplierPaymentDeleted::class]);
        $payment = $this->record(1000);
        $this->invoice->update(['status' => 'paid']);

        $this->actingAs($this->user())
            ->deleteJson("/api/v1/purchase/payments/{$payment->id}")
            ->assertOk();

        $this->invoice->refresh();
        $this->assertSame('0.00', (string) $this->invoice->paid_amount);
        // Back to unpaid, not stuck on partial.
        $this->assertSame('unpaid', $this->invoice->status);
        Event::assertDispatched(SupplierPaymentDeleted::class);
    }

    /** Editing must not count a payment as an overpayment against itself. */
    public function test_a_payment_may_be_re_saved_at_its_own_amount(): void
    {
        $payment = $this->record(1000);

        $this->actingAs($this->user())
            ->putJson("/api/v1/purchase/payments/{$payment->id}", [
                'payment_date' => now()->toDateString(),
                'amount' => 1000,
                'payment_method' => 'cheque',
            ])
            ->assertOk();
    }

    public function test_paid_amount_is_the_sum_of_every_payment(): void
    {
        $this->record(300);
        $this->record(200);

        $this->actingAs($this->user())
            ->postJson('/api/v1/purchase/payments', $this->payload(['amount' => 500]))
            ->assertCreated();

        $this->invoice->refresh();
        $this->assertSame('1000.00', (string) $this->invoice->paid_amount);
        $this->assertSame('paid', $this->invoice->status);
    }

    public function test_form_options_only_offers_invoices_with_something_owing(): void
    {
        SupplierInvoice::create([
            'invoice_number' => 'INV-PAID',
            'supplier_id' => $this->supplier->id,
            'invoice_date' => now(),
            'subtotal' => 50, 'vat_amount' => 0, 'total_amount' => 50,
            'paid_amount' => 50, 'status' => 'paid',
        ]);

        $response = $this->actingAs($this->user())
            ->getJson('/api/v1/purchase/payments/form-options')
            ->assertOk();

        $numbers = array_column($response->json('invoices'), 'invoice_number');
        $this->assertContains('INV-001', $numbers);
        $this->assertNotContains('INV-PAID', $numbers);
        $this->assertSame('1000.00', $response->json('invoices.0.outstanding'));
    }
}
