<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Settings\Requester;
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

    private function order(array $supplier = []): PurchaseOrder
    {
        return PurchaseOrder::create([
            'po_number' => 'LPO-DOC-'.(PurchaseOrder::count() + 1), 'supplier_id' => Supplier::factory()->create($supplier)->id,
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

    public function test_the_vendor_shows_name_email_and_every_phone_it_has(): void
    {
        $order = $this->order([
            'name' => 'Gulf Steel', 'email' => 'sales@gulfsteel.test',
            'phone' => '17001111', 'phone2' => '17002222', 'whatsapp' => '17001111',
        ]);
        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();

        $this->assertStringContainsString('Gulf Steel', $html);
        $this->assertStringContainsString('sales@gulfsteel.test', $html);
        // The WhatsApp number repeats phone, so it is listed once.
        $this->assertStringContainsString('P: 17001111 / 17002222<', $html);
    }

    /** A number kept only as Phone 2 or WhatsApp used to print no number at all. */
    public function test_a_vendor_with_only_a_whatsapp_number_still_shows_it(): void
    {
        $order = $this->order(['phone' => null, 'phone2' => null, 'whatsapp' => '+97333334444', 'email' => null]);
        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();

        $this->assertStringContainsString('P: +97333334444<', $html);
    }

    public function test_a_vendor_with_no_phone_prints_no_phone_line(): void
    {
        $order = $this->order(['phone' => null, 'phone2' => null, 'whatsapp' => null]);
        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();

        $this->assertStringNotContainsString('P: ', $html);
    }

    public function test_site_project_shows_the_mprs_project_not_its_company(): void
    {
        $order = $this->order();
        $order->update(['purchase_request_id' => PurchaseRequest::factory()->create([
            'company_name' => 'Miknas Industrial', 'project_name' => 'Forkoll',
        ])->id]);
        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();

        $this->assertMatchesRegularExpression('#Site / Project</div>\s*<div class="site-value">Forkoll</div>#', $html);
    }

    public function test_an_mpr_without_a_project_prints_no_site_box(): void
    {
        $order = $this->order();
        $order->update(['purchase_request_id' => PurchaseRequest::factory()->create([
            'company_name' => 'Miknas Industrial', 'project_name' => null,
        ])->id]);
        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();

        $this->assertStringNotContainsString('Site / Project', $html);
    }

    private function shipToHtml(?string $requestedBy): string
    {
        $order = $this->order(['phone' => null, 'phone2' => null, 'whatsapp' => null]);
        $order->update(['purchase_request_id' => PurchaseRequest::factory()->create([
            'requested_by_name' => $requestedBy,
        ])->id]);

        return view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();
    }

    public function test_ship_to_shows_the_requesters_name_and_contact_numbers(): void
    {
        Requester::create(['name' => 'Ali Hassan', 'phones' => ['+973 3312 3456', '17 555 010']]);

        $html = $this->shipToHtml('Ali Hassan');

        $this->assertMatchesRegularExpression(
            '#<div class="party-name">Ali Hassan</div>\s*<div class="party-line">P: \+973 3312 3456 / 17 555 010</div>#',
            $html,
        );
    }

    public function test_ship_to_prints_no_number_for_someone_not_on_the_list_or_without_one(): void
    {
        Requester::create(['name' => 'Zainab', 'phones' => []]);

        $this->assertStringNotContainsString('P: ', $this->shipToHtml('Zainab'));
        $this->assertStringNotContainsString('P: ', $this->shipToHtml('Someone Unlisted'));
    }
}
