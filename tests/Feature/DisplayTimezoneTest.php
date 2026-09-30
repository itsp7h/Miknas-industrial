<?php

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\PurchaseSignature;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Times are stored in UTC and worded, where the server writes them as text,
 * in the display timezone: Bahrain, three hours ahead. A GM who signs at
 * 14:35 must not see 11:35 on the printed MPR.
 */
class DisplayTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stored_utc_time_is_worded_in_bahrain_time(): void
    {
        $this->assertSame('30 Sep 2026, 14:35', LocalTime::format(Carbon::parse('2026-09-30 11:35:00', 'UTC')));
        $this->assertNull(LocalTime::format(null));
    }

    public function test_the_display_timezone_can_be_changed(): void
    {
        config(['app.display_timezone' => 'UTC']);

        $this->assertSame('30 Sep 2026, 11:35', LocalTime::format(Carbon::parse('2026-09-30 11:35:00', 'UTC')));
    }

    public function test_the_printed_mpr_shows_the_signature_and_print_times_in_bahrain_time(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 12:00:00', 'UTC'));
        $requester = User::factory()->create();
        $requester->givePermissionTo(['pipeline.view', 'pipeline.create', 'pipeline.edit', 'pipeline.view-own']);
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);
        PurchaseSignature::create([
            'purchase_request_id' => $pr->id, 'signed_by' => $requester->id,
            'signature_image' => 'data:image/png;base64,iVBORw0KGgo=',
            'signed_at' => Carbon::parse('2026-09-30 11:35:00', 'UTC'),
        ]);

        $this->actingAs($requester)->get(route('purchase.requests.print', $pr))
            ->assertOk()
            ->assertSee('Signed 30 Sep 2026, 14:35')
            ->assertSee('30 Sep 2026, 15:00');
    }
}
