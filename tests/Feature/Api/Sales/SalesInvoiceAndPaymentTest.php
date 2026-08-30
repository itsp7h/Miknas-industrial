<?php

namespace Tests\Feature\Api\Sales;

use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private SalesOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['name' => 'Gulf Steel', 'outstanding_balance' => 0]);
        $this->order = SalesOrder::create([
            'order_number' => 'SO-00001', 'customer_id' => $this->customer->id,
            'order_date' => now(), 'status' => 'confirmed', 'total_amount' => 100,
        ]);
        Setting::set('vat_rate', '10');
    }

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'sales_order_id' => $this->order->id,
            'invoice_date' => now()->toDateString(),
        ], $overrides);
    }

    private function createInvoice(): array
    {
        return $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/invoices', $this->invoicePayload())
            ->assertCreated()->json('data');
    }

    public function test_invoice_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/sales/invoices')->assertUnauthorized();
        $this->getJson('/api/v1/sales/payments')->assertUnauthorized();
    }

    /**
     * The Blade controller took subtotal, VAT and total straight from the
     * request, so the client decided what it owed. They are derived here.
     */
    public function test_it_derives_invoice_amounts_from_the_order_and_vat_setting(): void
    {
        $invoice = $this->createInvoice();

        $this->assertEquals(100, $invoice['subtotal']);
        $this->assertEquals(10, $invoice['vat_rate']);
        $this->assertEquals(10, $invoice['vat_amount']);
        $this->assertEquals(110, $invoice['total_amount']);
        $this->assertSame('unpaid', $invoice['status']);
    }

    public function test_it_ignores_client_supplied_amounts(): void
    {
        $invoice = $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/invoices', $this->invoicePayload([
                'subtotal' => 1, 'vat_amount' => 0, 'total_amount' => 1,
            ]))->assertCreated()->json('data');

        $this->assertEquals(110, $invoice['total_amount']);
    }

    public function test_invoicing_marks_the_order_invoiced_and_raises_the_customer_balance(): void
    {
        $this->createInvoice();

        $this->assertSame('invoiced', $this->order->fresh()->status);
        $this->assertEquals(110, $this->customer->fresh()->outstanding_balance);
    }

    public function test_an_order_cannot_be_invoiced_twice(): void
    {
        $this->createInvoice();

        $this->actingAs($this->actingUser())
            ->postJson('/api/v1/sales/invoices', $this->invoicePayload())
            ->assertStatus(422);
    }

    public function test_invoice_form_options_hides_already_invoiced_orders(): void
    {
        $before = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/sales/invoices/form-options')->json('orders');
        $this->assertCount(1, $before);

        $this->createInvoice();

        $after = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/sales/invoices/form-options')->json('orders');
        $this->assertCount(0, $after);
    }

    public function test_a_partial_payment_marks_the_invoice_partial_and_reduces_the_balance(): void
    {
        $invoice = $this->createInvoice();

        $this->actingAs($this->actingUser())->postJson('/api/v1/sales/payments', [
            'sales_invoice_id' => $invoice['id'],
            'receipt_date' => now()->toDateString(),
            'amount' => 40,
            'payment_method' => 'cash',
        ])->assertCreated();

        $fresh = SalesInvoice::find($invoice['id']);
        $this->assertSame('partial', $fresh->status);
        $this->assertEquals(40, $fresh->paid_amount);
        $this->assertEquals(70, $this->customer->fresh()->outstanding_balance);
    }

    public function test_paying_the_balance_in_full_marks_the_invoice_paid(): void
    {
        $invoice = $this->createInvoice();

        $this->actingAs($this->actingUser())->postJson('/api/v1/sales/payments', [
            'sales_invoice_id' => $invoice['id'],
            'receipt_date' => now()->toDateString(),
            'amount' => 110,
            'payment_method' => 'bank_transfer',
        ])->assertCreated();

        $this->assertSame('paid', SalesInvoice::find($invoice['id'])->status);
        $this->assertEquals(0, $this->customer->fresh()->outstanding_balance);
    }

    /**
     * payment_method is an enum in the schema; the Blade controller validated
     * it as a free string, so a bad value became a 500 from a CHECK constraint.
     */
    public function test_it_rejects_a_payment_method_outside_the_enum(): void
    {
        $invoice = $this->createInvoice();

        $this->actingAs($this->actingUser())->postJson('/api/v1/sales/payments', [
            'sales_invoice_id' => $invoice['id'],
            'receipt_date' => now()->toDateString(),
            'amount' => 10,
            'payment_method' => 'crypto',
        ])->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
    }

    public function test_it_refuses_to_overpay_an_invoice(): void
    {
        $invoice = $this->createInvoice();

        $this->actingAs($this->actingUser())->postJson('/api/v1/sales/payments', [
            'sales_invoice_id' => $invoice['id'],
            'receipt_date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);

        $this->assertEquals(110, $this->customer->fresh()->outstanding_balance);
    }

    public function test_payment_form_options_lists_only_unsettled_invoices_with_their_balance(): void
    {
        $invoice = $this->createInvoice();

        $options = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/sales/payments/form-options')->assertOk();

        $this->assertEquals(110, $options->json('invoices.0.balance_due'));
        $this->assertSame(['cash', 'bank_transfer', 'cheque', 'other'], $options->json('payment_methods'));

        $this->actingAs($this->actingUser())->postJson('/api/v1/sales/payments', [
            'sales_invoice_id' => $invoice['id'],
            'receipt_date' => now()->toDateString(),
            'amount' => 110,
            'payment_method' => 'cash',
        ])->assertCreated();

        $after = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/sales/payments/form-options')->json('invoices');
        $this->assertCount(0, $after);
    }
}
