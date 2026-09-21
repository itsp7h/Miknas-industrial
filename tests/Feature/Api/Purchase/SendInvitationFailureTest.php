<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RfqInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * A send that does not arrive must not report that it did.
 *
 * sendInvitation() used to write status='sent' and sent_at before it tried to
 * deliver, then catch every Throwable into the log. The screen said sent, the
 * row said sent, the supplier had nothing, and the only honest record was a
 * line in laravel.log. These pin the corrected order and the reporting.
 */
class SendInvitationFailureTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        return User::factory()->create()->givePermissionTo([
            'pipeline.view-all', 'pipeline.manage-rfq',
        ]);
    }

    private function requestWithPending(int $count = 1, array $supplierAttributes = []): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        foreach (range(1, $count) as $n) {
            RfqInvitation::factory()->create([
                'purchase_request_id' => $purchaseRequest->id,
                'supplier_id' => Supplier::factory()->create($supplierAttributes)->id,
                'status' => 'pending',
                'sent_at' => null,
                'channel' => 'email',
            ]);
        }

        return $purchaseRequest;
    }

    public function test_a_failed_send_leaves_the_invitation_unsent(): void
    {
        Log::spy();
        // No mail account configured — the failure this project actually hit.
        $invitation = $this->requestWithPending()->rfqInvitations()->first();

        try {
            app(RfqInvitationService::class)->sendInvitation($invitation);
            $this->fail('sendInvitation should have thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No enabled mail account', $e->getMessage());
        }

        $invitation->refresh();
        $this->assertSame('pending', $invitation->status);
        $this->assertNull($invitation->sent_at);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_supplier_with_no_email_is_refused_rather_than_recorded_as_sent(): void
    {
        $this->workingMailAccount();
        $invitation = $this->requestWithPending(1, ['email' => null])->rfqInvitations()->first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no email address');

        try {
            app(RfqInvitationService::class)->sendInvitation($invitation);
        } finally {
            $this->assertSame('pending', $invitation->fresh()->status);
        }
    }

    public function test_the_endpoint_refuses_when_nothing_could_be_sent(): void
    {
        $purchaseRequest = $this->requestWithPending(2);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$purchaseRequest->id}/send-invitations")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Could not send any invitation. Could not email '
                .$purchaseRequest->rfqInvitations()->with('supplier')->first()->supplier->name
                .': No enabled mail account is configured.']);

        // The request has not moved on, and both invitations are still waiting.
        $this->assertSame('rfq', $purchaseRequest->fresh()->stage);
        $this->assertSame(2, $purchaseRequest->rfqInvitations()->where('status', 'pending')->count());
    }

    public function test_one_bad_supplier_does_not_cost_the_others_their_invitation(): void
    {
        $this->workingMailAccount();

        $purchaseRequest = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $good = Supplier::factory()->create(['name' => 'Reachable Co', 'email' => 'good@example.test']);
        $bad = Supplier::factory()->create(['name' => 'Addressless Co', 'email' => null]);

        foreach ([$good, $bad] as $supplier) {
            RfqInvitation::factory()->create([
                'purchase_request_id' => $purchaseRequest->id,
                'supplier_id' => $supplier->id,
                'status' => 'pending', 'sent_at' => null, 'channel' => 'email',
            ]);
        }

        $response = $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$purchaseRequest->id}/send-invitations")
            ->assertOk();

        $this->assertStringContainsString('1 of 2 supplier(s) notified', $response->json('message'));
        $this->assertStringContainsString('Addressless Co', $response->json('message'));

        // The one that went is sent; the one that did not is still pending.
        $this->assertSame('sent', $good->rfqInvitations()->first()->status);
        $this->assertSame('pending', $bad->rfqInvitations()->first()->status);
        $this->assertSame('quoting', $purchaseRequest->fresh()->stage);
    }
}
