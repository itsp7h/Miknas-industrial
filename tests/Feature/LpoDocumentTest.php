<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Settings\Company;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Models\Settings\Requester;
use App\Models\Supplier;
use App\Models\User;
use App\Services\LpoDeliveryService;
use App\Support\ImageDataUrl;
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

    private function locationHtml(array $mpr): string
    {
        $order = $this->order();
        $order->update(['purchase_request_id' => PurchaseRequest::factory()->create($mpr)->id]);

        return view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();
    }

    private function projectWith(string $project, string $location, ?string $address, ?Company $company = null): void
    {
        $company ??= Company::firstOrCreate(['name' => 'Miknas Industrial'], ['is_active' => true]);
        ProjectSetting::create(['name' => $project, 'company_id' => $company->id, 'is_active' => true])
            ->locations()->create(['name' => $location, 'address' => $address, 'is_active' => true]);
    }

    public function test_ship_to_shows_the_address_saved_for_the_mprs_location(): void
    {
        $this->projectWith('Forkoll', 'Askar Forkoll', 'Road 4523, Block 945, Askar, Bahrain');

        $html = $this->locationHtml(['project_name' => 'Forkoll', 'location' => 'Askar Forkoll']);

        $this->assertMatchesRegularExpression(
            '#<div class="party-line">Askar Forkoll</div>\s*(\{\{--.*?--\}\}\s*)?<div class="party-line">Road 4523, Block 945, Askar, Bahrain</div>#s',
            $html,
        );
    }

    /** Two projects can each have a location of the same name. */
    public function test_the_mprs_own_project_wins_a_shared_location_name(): void
    {
        $this->projectWith('Hidd Works', 'Main Yard', 'Road 1, Block 115, Hidd, Bahrain');
        $this->projectWith('Forkoll', 'Main Yard', 'Road 4523, Block 945, Askar, Bahrain');

        $html = $this->locationHtml(['project_name' => 'Forkoll', 'location' => 'Main Yard']);

        $this->assertStringContainsString('Road 4523, Block 945, Askar, Bahrain', $html);
        $this->assertStringNotContainsString('Hidd, Bahrain', $html);
    }

    /** The MPR form offers every location under the company's projects. */
    public function test_a_location_under_another_project_of_the_company_is_found(): void
    {
        $this->projectWith('Askar Site', 'Askar Forkoll', 'Road 4523, Block 945, Askar, Bahrain');

        $html = $this->locationHtml(['project_name' => 'Forkoll', 'location' => 'Askar Forkoll']);

        $this->assertStringContainsString('Road 4523, Block 945, Askar, Bahrain', $html);
    }

    public function test_no_address_line_without_a_saved_address(): void
    {
        $this->projectWith('Forkoll', 'Askar Forkoll', null);
        $this->projectWith('Other', 'Same Name', 'Same Name');

        $this->assertDoesNotMatchRegularExpression(
            '#<div class="party-line">Askar Forkoll</div>\s*(\{\{--.*?--\}\}\s*)?<div class="party-line">#s',
            $this->locationHtml(['project_name' => 'Forkoll', 'location' => 'Askar Forkoll']),
        );
        // An "address" that only repeats the location's name is not printed twice.
        $this->assertSame(1, substr_count(
            $this->locationHtml(['project_name' => 'Other', 'location' => 'Same Name']),
            '<div class="party-line">Same Name</div>',
        ));
    }

    /** A PNG of the given size, as the browser would upload one. */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function companyHtml(?string $logo, ?string $stamp): string
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $company->forceFill(['logo_image' => $logo, 'stamp_image' => $stamp])->save();
        $order = $this->order();
        $order->update(['purchase_request_id' => PurchaseRequest::factory()->create(['company_name' => 'Miknas Industrial'])->id]);

        return view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order))->render();
    }

    public function test_the_letterhead_shows_the_companys_logo_in_place_of_the_mark(): void
    {
        $logo = $this->png(400, 140);
        $html = $this->companyHtml($logo, null);

        // 400×140 fitted into 160×56: width-bound, shape kept.
        $this->assertStringContainsString('<img class="brand-logo" src="'.$logo.'" width="160" height="56" alt="Miknas Industrial">', $html);
        $this->assertLessThan(strpos($html, 'class="brand-name"'), strpos($html, 'class="brand-logo"'));
        $this->assertStringNotContainsString('class="brand-box"', $html);
    }

    public function test_the_companys_stamp_sits_beside_prepared_by(): void
    {
        $stamp = $this->png(300, 300);
        $html = $this->companyHtml(null, $stamp);

        $tag = '<img class="sig-stamp" src="'.$stamp.'" width="90" height="90" alt="Miknas Industrial stamp">';
        $this->assertStringContainsString($tag, $html);
        // In the Prepared By block: after the issuer's name, before its line.
        $at = strpos($html, $tag);
        $this->assertGreaterThan(strpos($html, 'class="sig-name"'), $at);
        $this->assertLessThan(strpos($html, 'Prepared By</div>'), $at);
    }

    public function test_a_company_without_either_keeps_the_mark_and_no_stamp(): void
    {
        $html = $this->companyHtml(null, null);

        $this->assertStringContainsString('class="brand-box"', $html);
        $this->assertStringNotContainsString('class="brand-logo"', $html);
        $this->assertStringNotContainsString('class="sig-stamp"', $html);
    }

    public function test_an_image_is_fitted_never_enlarged(): void
    {
        $this->assertSame([160, 56], ImageDataUrl::fit($this->png(400, 140), 160, 56));
        $this->assertSame([56, 56], ImageDataUrl::fit($this->png(300, 300), 160, 56));
        $this->assertSame([80, 20], ImageDataUrl::fit($this->png(80, 20), 160, 56));
        $this->assertNull(ImageDataUrl::fit('not an image', 160, 56));
        $this->assertNull(ImageDataUrl::sized(null, 160, 56));
    }
}
