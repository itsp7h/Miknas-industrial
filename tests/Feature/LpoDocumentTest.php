<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\LpoDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The LPO shown on screen (print) and the one downloaded or emailed (pdf)
 * are the same document: one partial, laid out the way DomPDF can draw.
 */
class LpoDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function order(): PurchaseOrder
    {
        return PurchaseOrder::create([
            'po_number' => 'LPO-DOC-1', 'supplier_id' => Supplier::factory()->create()->id,
            'po_date' => '2026-09-28', 'total_amount' => 5, 'status' => 'sent',
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_the_screen_copy_holds_exactly_the_pdf_document(): void
    {
        $data = app(LpoDeliveryService::class)->documentData($this->order());

        $pdf = view('purchase.orders.pdf', $data)->render();
        $body = trim(preg_replace('#^.*<body>(.*)</body>.*$#s', '$1', $pdf));

        $this->assertStringContainsString('Purchase Order', $body);
        $this->assertStringContainsString($body, view('purchase.orders.print', $data)->render());
    }

    /** What DomPDF cannot draw, and so what made the two copies differ. */
    public function test_the_document_uses_nothing_dompdf_drops(): void
    {
        $data = app(LpoDeliveryService::class)->documentData($this->order());
        $html = view('purchase.orders.pdf', $data)->render();

        $this->assertStringNotContainsString('display: flex', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertDoesNotMatchRegularExpression('/font-weight:\s*[1-35689]00/', $html);
    }
}
