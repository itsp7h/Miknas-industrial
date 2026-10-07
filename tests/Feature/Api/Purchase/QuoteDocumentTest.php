<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RfqInvitation;
use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A supplier may attach their own quotation in the portal. Optional; one
 * PDF or image up to 10 MB, kept privately and opened from the quotes page
 * by staff who may work quotes.
 */
class QuoteDocumentTest extends TestCase
{
    use RefreshDatabase;

    private RfqInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $purchaseRequest = PurchaseRequest::factory()->create(['stage' => 'quoting']);
        PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id, 'description' => 'Steel rod',
            'unit' => 'kg', 'quantity_required' => 10,
        ]);
        $this->invitation = RfqInvitation::factory()->create([
            'purchase_request_id' => $purchaseRequest->id, 'status' => 'sent', 'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * As the browser sends it with a file: multipart, so every value is a
     * string and booleans arrive as "1" / "0".
     */
    private function submit(?UploadedFile $document = null)
    {
        $code = $this->getJson("/api/v1/rfq/{$this->invitation->token}")->json('confirm_code');
        $item = $this->invitation->purchaseRequest->items->first();

        return $this->post("/api/v1/rfq/{$this->invitation->token}", array_filter([
            'terms' => '1',
            'confirm_code' => $code,
            'reference' => 'Q-77',
            'items' => [['id' => (string) $item->id, 'unit_price' => '2', 'is_vatable' => '0', 'not_available' => '0']],
            'document' => $document,
        ]), ['Accept' => 'application/json']);
    }

    private function officer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['pipeline.manage-quotes', 'pipeline.view-active-pipeline']);

        return $user;
    }

    public function test_a_quote_is_accepted_without_a_document(): void
    {
        $this->submit()->assertCreated();

        $quote = SupplierQuote::firstOrFail();
        $this->assertNull($quote->document_path);
        $this->assertNull($quote->documentInfo());
    }

    public function test_an_attached_quotation_is_kept_privately_under_its_name(): void
    {
        $this->submit(UploadedFile::fake()->create('Gulf Steel Q-77.pdf', 300, 'application/pdf'))
            ->assertCreated();

        $quote = SupplierQuote::firstOrFail();
        $this->assertSame('Gulf Steel Q-77.pdf', $quote->document_name);
        $this->assertStringStartsWith("quote-documents/{$quote->id}/", $quote->document_path);
        $this->assertGreaterThan(0, $quote->document_size);
        Storage::disk('local')->assertExists($quote->document_path);
        // The multipart booleans landed as booleans.
        $this->assertFalse($quote->items->first()->is_vatable);
        $this->assertEquals(20, $quote->items->first()->total_price);
    }

    public function test_a_document_must_be_a_pdf_or_an_image(): void
    {
        $this->submit(UploadedFile::fake()->create('quote.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.document.0', 'Your quotation must be a PDF, JPG or PNG file.');

        $this->assertDatabaseCount('supplier_quotes', 0);
        // Still open for a corrected submission.
        $this->assertNotSame('submitted', $this->invitation->fresh()->status);
    }

    public function test_a_document_over_ten_megabytes_is_refused(): void
    {
        $this->submit(UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.document.0', 'Your quotation must be 10 MB or smaller.');

        $this->assertDatabaseCount('supplier_quotes', 0);
    }

    public function test_the_quotes_page_links_it_per_supplier_and_per_line(): void
    {
        $this->submit(UploadedFile::fake()->create('q.pdf', 10, 'application/pdf'))->assertCreated();
        $quote = SupplierQuote::firstOrFail();
        $url = "/purchase/quotes/{$quote->id}/document";

        $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/requests/{$this->invitation->purchase_request_id}/quotes")
            ->assertOk()
            ->assertJsonPath('data.suppliers.0.document.name', 'q.pdf')
            ->assertJsonPath('data.suppliers.0.document.url', $url)
            ->assertJsonPath('data.items.0.rows.0.document.url', $url);
    }

    public function test_staff_who_work_quotes_open_it_inline_at_any_stage(): void
    {
        $this->submit(UploadedFile::fake()->create('q.pdf', 10, 'application/pdf'))->assertCreated();
        $quote = SupplierQuote::firstOrFail();
        // Long after the quotes stage: the document still opens.
        $quote->purchaseRequest->update(['stage' => 'receiving']);

        $this->actingAs($this->officer())
            ->get("/purchase/quotes/{$quote->id}/document")
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=q.pdf');
    }

    public function test_opening_it_needs_permission_to_work_quotes(): void
    {
        $this->submit(UploadedFile::fake()->create('q.pdf', 10, 'application/pdf'))->assertCreated();
        $quote = SupplierQuote::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->get("/purchase/quotes/{$quote->id}/document")
            ->assertForbidden();
    }

    public function test_a_quote_without_a_document_has_none_to_open(): void
    {
        $this->submit()->assertCreated();
        $quote = SupplierQuote::firstOrFail();

        $this->actingAs($this->officer())
            ->get("/purchase/quotes/{$quote->id}/document")
            ->assertNotFound();
    }
}
