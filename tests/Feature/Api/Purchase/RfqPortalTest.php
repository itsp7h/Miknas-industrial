<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RfqInvitation;
use App\Models\Setting;
use App\Models\SupplierQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public quote portal's API. Every one of these used to be exercised —
 * if at all — through the Blade form, so they are ported here rather than
 * dropped with the views (CLAUDE.md #12, cutover step 5).
 *
 * The portal is unauthenticated: none of these tests act as a user, which is
 * the point. The token is the whole credential.
 */
class RfqPortalTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(array $attributes = [], int $itemCount = 2): RfqInvitation
    {
        $purchaseRequest = PurchaseRequest::factory()->create(['stage' => 'quoting']);

        foreach (range(1, $itemCount) as $n) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'description' => "Steel rod {$n}",
                'unit' => 'kg',
                'quantity_required' => 10 * $n,
            ]);
        }

        return RfqInvitation::factory()->create(array_merge([
            'purchase_request_id' => $purchaseRequest->id,
            'status' => 'sent',
            'expires_at' => now()->addDays(7),
        ], $attributes));
    }

    /** GET, then POST with whatever code the GET issued — the supplier's path. */
    private function quote(RfqInvitation $invitation, array $overrides = []): array
    {
        $code = $this->getJson("/api/v1/rfq/{$invitation->token}")->json('confirm_code');

        $items = $invitation->purchaseRequest->items->map(fn ($item) => [
            'id' => $item->id,
            'unit_price' => 2,
            'is_vatable' => false,
            'not_available' => false,
        ])->all();

        return array_merge([
            'terms' => true,
            'confirm_code' => $code,
            'items' => $items,
        ], $overrides);
    }

    public function test_an_open_invitation_returns_its_items_and_a_confirmation_code(): void
    {
        Setting::set('vat_rate', 10);
        $invitation = $this->invitation();

        $response = $this->getJson("/api/v1/rfq/{$invitation->token}");

        $response->assertOk()
            ->assertJsonPath('state', 'open')
            ->assertJsonPath('vat_rate', 10)
            ->assertJsonPath('data.supplier_name', $invitation->supplier->name)
            ->assertJsonPath('data.request.request_number', $invitation->purchaseRequest->request_number)
            ->assertJsonCount(2, 'data.items');

        $this->assertMatchesRegularExpression('/^[0-9A-F]{5}$/', $response->json('confirm_code'));
    }

    /** Opening the link is how the buyer knows the supplier saw it. */
    public function test_reading_an_unopened_invitation_marks_it_opened(): void
    {
        $invitation = $this->invitation(['status' => 'sent']);

        $this->getJson("/api/v1/rfq/{$invitation->token}")->assertOk();

        $invitation->refresh();
        $this->assertSame('opened', $invitation->status);
        $this->assertNotNull($invitation->opened_at);
    }

    /**
     * React mounts effects twice under StrictMode, so the read has to be safe
     * to repeat: a second code would leave the screen showing one value and
     * the session holding another.
     */
    public function test_reading_twice_issues_the_same_confirmation_code(): void
    {
        $invitation = $this->invitation();

        $first = $this->getJson("/api/v1/rfq/{$invitation->token}")->json('confirm_code');
        $second = $this->getJson("/api/v1/rfq/{$invitation->token}")->json('confirm_code');

        $this->assertSame($first, $second);
    }

    public function test_an_invitation_covering_a_subset_offers_only_those_items(): void
    {
        $invitation = $this->invitation(itemCount: 3);
        $chosen = $invitation->purchaseRequest->items->take(2)->pluck('id')->all();
        $invitation->update(['item_ids' => $chosen]);

        $response = $this->getJson("/api/v1/rfq/{$invitation->token}");

        $response->assertOk()->assertJsonCount(2, 'data.items');
        $this->assertSame($chosen, array_column($response->json('data.items'), 'id'));
    }

    public function test_an_expired_invitation_reports_expired_and_offers_no_code(): void
    {
        $invitation = $this->invitation(['status' => 'opened', 'expires_at' => now()->subDay()]);

        $this->getJson("/api/v1/rfq/{$invitation->token}")
            ->assertOk()
            ->assertJsonPath('state', 'expired')
            ->assertJsonMissingPath('confirm_code');
    }

    public function test_an_already_submitted_invitation_reports_submitted(): void
    {
        $invitation = $this->invitation(['status' => 'submitted']);

        $this->getJson("/api/v1/rfq/{$invitation->token}")
            ->assertOk()
            ->assertJsonPath('state', 'submitted')
            ->assertJsonMissingPath('confirm_code');
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->getJson('/api/v1/rfq/nobody-issued-this')->assertNotFound();
    }

    public function test_submitting_records_the_quote_and_closes_the_invitation(): void
    {
        Setting::set('vat_rate', 10);
        $invitation = $this->invitation();
        [$first, $second] = $invitation->purchaseRequest->items->all();

        $payload = $this->quote($invitation, [
            'lead_time_days' => 14,
            'payment_terms' => '30 days net',
            'notes' => 'Ex-works Sitra.',
            'items' => [
                ['id' => $first->id, 'unit_price' => 2, 'is_vatable' => true, 'not_available' => false],
                ['id' => $second->id, 'unit_price' => 3, 'is_vatable' => false, 'not_available' => false],
            ],
        ]);

        $this->postJson("/api/v1/rfq/{$invitation->token}", $payload)
            ->assertCreated()
            ->assertJsonPath('state', 'submitted');

        $quote = SupplierQuote::firstOrFail();

        // 10 × 2 = 20 (+10% VAT = 2) and 20 × 3 = 60, VAT-free.
        $this->assertEquals(82, $quote->total_amount);
        $this->assertSame(14, $quote->lead_time_days);
        $this->assertSame('30 days net', $quote->payment_terms);
        $this->assertEquals(20, $quote->items()->where('purchase_request_item_id', $first->id)->value('total_price'));
        $this->assertEquals(60, $quote->items()->where('purchase_request_item_id', $second->id)->value('total_price'));

        $this->assertSame('submitted', $invitation->refresh()->status);
        $this->assertSame('comparison', $invitation->purchaseRequest->refresh()->stage);
    }

    /**
     * The Blade form paired prices to items by array position. The API pairs
     * them by id, so a payload that arrives in another order still prices the
     * right lines instead of silently transposing the quote.
     */
    public function test_prices_are_paired_to_items_by_id_not_by_position(): void
    {
        $invitation = $this->invitation();
        [$first, $second] = $invitation->purchaseRequest->items->all();

        $payload = $this->quote($invitation, [
            'items' => [
                ['id' => $second->id, 'unit_price' => 3, 'is_vatable' => false, 'not_available' => false],
                ['id' => $first->id, 'unit_price' => 2, 'is_vatable' => false, 'not_available' => false],
            ],
        ]);

        $this->postJson("/api/v1/rfq/{$invitation->token}", $payload)->assertCreated();

        $quote = SupplierQuote::firstOrFail();
        $this->assertEquals(2, $quote->items()->where('purchase_request_item_id', $first->id)->value('unit_price'));
        $this->assertEquals(3, $quote->items()->where('purchase_request_item_id', $second->id)->value('unit_price'));
    }

    public function test_an_unavailable_line_is_recorded_at_zero_without_vat(): void
    {
        Setting::set('vat_rate', 10);
        $invitation = $this->invitation();
        [$first, $second] = $invitation->purchaseRequest->items->all();

        $payload = $this->quote($invitation, [
            'items' => [
                ['id' => $first->id, 'unit_price' => 5, 'is_vatable' => true, 'not_available' => true],
                ['id' => $second->id, 'unit_price' => 3, 'is_vatable' => false, 'not_available' => false],
            ],
        ]);

        $this->postJson("/api/v1/rfq/{$invitation->token}", $payload)->assertCreated();

        $line = SupplierQuote::firstOrFail()->items()->where('purchase_request_item_id', $first->id)->firstOrFail();
        $this->assertTrue((bool) $line->not_available);
        $this->assertEquals(0, $line->unit_price);
        $this->assertEquals(0, $line->total_price);
        $this->assertFalse((bool) $line->is_vatable);
        $this->assertEquals(60, SupplierQuote::firstOrFail()->total_amount);
    }

    /** A renamed line is kept beside the original, not over it. */
    public function test_a_supplier_description_is_stored_alongside_the_original(): void
    {
        $invitation = $this->invitation(itemCount: 1);
        $item = $invitation->purchaseRequest->items->first();

        $payload = $this->quote($invitation, [
            'items' => [[
                'id' => $item->id,
                'unit_price' => 4,
                'is_vatable' => false,
                'not_available' => false,
                'supplier_description' => 'Steel rod 12mm (equivalent)',
            ]],
        ]);

        $this->postJson("/api/v1/rfq/{$invitation->token}", $payload)->assertCreated();

        $line = SupplierQuote::firstOrFail()->items()->firstOrFail();
        $this->assertSame('Steel rod 1', $line->description);
        $this->assertSame('Steel rod 12mm (equivalent)', $line->supplier_description);
    }

    public function test_a_wrong_confirmation_code_is_rejected_and_writes_nothing(): void
    {
        $invitation = $this->invitation();

        $this->postJson("/api/v1/rfq/{$invitation->token}", $this->quote($invitation, ['confirm_code' => 'ZZZZZ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirm_code');

        $this->assertSame(0, SupplierQuote::count());
        $this->assertNotSame('submitted', $invitation->refresh()->status);
    }

    public function test_the_terms_must_be_accepted(): void
    {
        $invitation = $this->invitation();

        $this->postJson("/api/v1/rfq/{$invitation->token}", $this->quote($invitation, ['terms' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('terms');

        $this->assertSame(0, SupplierQuote::count());
    }

    /** The code is spent on use, so a replayed payload cannot quote twice. */
    public function test_a_second_submission_is_refused(): void
    {
        $invitation = $this->invitation();
        $payload = $this->quote($invitation);

        $this->postJson("/api/v1/rfq/{$invitation->token}", $payload)->assertCreated();
        $this->postJson("/api/v1/rfq/{$invitation->token}", $payload)->assertForbidden();

        $this->assertSame(1, SupplierQuote::count());
    }

    public function test_an_expired_invitation_cannot_be_submitted(): void
    {
        $invitation = $this->invitation();
        $payload = $this->quote($invitation);

        $invitation->update(['expires_at' => now()->subDay()]);

        $this->postJson("/api/v1/rfq/{$invitation->token}", $payload)->assertForbidden();
        $this->assertSame(0, SupplierQuote::count());
    }
}
