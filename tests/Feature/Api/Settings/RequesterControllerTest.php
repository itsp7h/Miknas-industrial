<?php

namespace Tests\Feature\Api\Settings;

use App\Events\RequesterDeleted;
use App\Events\RequesterSaved;
use App\Models\PurchaseRequest;
use App\Models\Settings\Company;
use App\Models\Settings\Requester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** System → Requested By: who the MPR form offers, per company. */
class RequesterControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $miknas;

    private Company $steelTech;

    protected function setUp(): void
    {
        parent::setUp();

        $this->miknas = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        $this->steelTech = Company::create(['name' => 'Steel Tech', 'is_active' => true]);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function manager(): User
    {
        return $this->userWith(['requesters.view', 'requesters.create', 'requesters.edit', 'requesters.delete']);
    }

    public function test_each_action_needs_its_own_square(): void
    {
        $requester = Requester::create(['name' => 'Ali']);
        $body = ['name' => 'Omar', 'company_ids' => [$this->miknas->id]];

        $this->getJson('/api/v1/settings/requesters')->assertUnauthorized();

        $nobody = User::factory()->create();
        $this->actingAs($nobody)->getJson('/api/v1/settings/requesters')->assertForbidden();

        // View alone reads the list and changes nothing.
        $viewer = $this->userWith(['requesters.view']);
        $this->actingAs($viewer)->getJson('/api/v1/settings/requesters')->assertOk();
        $this->actingAs($viewer)->postJson('/api/v1/settings/requesters', $body)->assertForbidden();
        $this->actingAs($viewer)->putJson("/api/v1/settings/requesters/{$requester->id}", $body)->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/v1/settings/requesters/{$requester->id}")->assertForbidden();
    }

    public function test_it_lists_people_with_their_companies_and_offers_the_active_companies(): void
    {
        Company::create(['name' => 'Closed Co', 'is_active' => false]);
        $ali = Requester::create(['name' => 'Ali']);
        $ali->companies()->sync([$this->steelTech->id, $this->miknas->id]);
        Requester::create(['name' => 'Zainab'])->companies()->sync([$this->miknas->id]);

        $response = $this->actingAs($this->manager())->getJson('/api/v1/settings/requesters')->assertOk();

        $this->assertSame(['Ali', 'Zainab'], array_column($response->json('data'), 'name'));
        $this->assertSame(['Miknas Industrial', 'Steel Tech'], array_column($response->json('data.0.companies'), 'name'));
        $this->assertEqualsCanonicalizing(
            [$this->miknas->id, $this->steelTech->id],
            $response->json('data.0.company_ids')
        );
        $this->assertSame(['Miknas Industrial', 'Steel Tech'], array_column($response->json('meta.companies'), 'name'));
    }

    public function test_it_adds_a_person_to_several_companies_and_broadcasts(): void
    {
        Event::fake([RequesterSaved::class]);

        $this->actingAs($this->manager())
            ->postJson('/api/v1/settings/requesters', [
                'name' => '  Omar Said ',
                'company_ids' => [$this->miknas->id, $this->steelTech->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Omar Said')
            ->assertJsonPath('message', 'Omar Said added.');

        $omar = Requester::where('name', 'Omar Said')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->miknas->id, $this->steelTech->id], $omar->companies->pluck('id')->all());
        Event::assertDispatched(RequesterSaved::class);
    }

    public function test_it_renames_and_remaps_a_person(): void
    {
        Event::fake([RequesterSaved::class]);
        $ali = Requester::create(['name' => 'Ali']);
        $ali->companies()->sync([$this->miknas->id]);

        $this->actingAs($this->manager())
            ->putJson("/api/v1/settings/requesters/{$ali->id}", [
                'name' => 'Ali Hassan',
                'company_ids' => [$this->steelTech->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ali Hassan');

        $this->assertSame([$this->steelTech->id], $ali->fresh()->companies->pluck('id')->all());
        Event::assertDispatched(RequesterSaved::class);
    }

    public function test_it_needs_a_unique_name_and_at_least_one_real_company(): void
    {
        Requester::create(['name' => 'Ali']);
        $manager = $this->manager();

        $this->actingAs($manager)
            ->postJson('/api/v1/settings/requesters', ['name' => 'Ali', 'company_ids' => [$this->miknas->id]])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->actingAs($manager)
            ->postJson('/api/v1/settings/requesters', ['name' => 'Omar', 'company_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_ids' => 'Choose at least one company.']);

        $this->actingAs($manager)
            ->postJson('/api/v1/settings/requesters', ['name' => 'Omar', 'company_ids' => [999]])
            ->assertStatus(422)->assertJsonValidationErrors('company_ids.0');
    }

    public function test_a_person_keeps_their_own_name_when_renamed_to_it(): void
    {
        $ali = Requester::create(['name' => 'Ali']);

        $this->actingAs($this->manager())
            ->putJson("/api/v1/settings/requesters/{$ali->id}", ['name' => 'Ali', 'company_ids' => [$this->miknas->id]])
            ->assertOk();
    }

    /** A request stores the name, so removing the person leaves it untouched. */
    public function test_deleting_a_person_leaves_their_requests_alone(): void
    {
        Event::fake([RequesterDeleted::class]);
        $ali = Requester::create(['name' => 'Ali']);
        $ali->companies()->sync([$this->miknas->id]);
        $request = PurchaseRequest::create([
            'request_number' => 'MPR26-0001', 'date' => '2026-09-01', 'company_name' => 'Miknas Industrial',
            'requested_by_name' => 'Ali', 'requested_by' => User::factory()->create()->id, 'stage' => 'draft',
            'status' => 'pending',
        ]);

        $this->actingAs($this->manager())
            ->deleteJson("/api/v1/settings/requesters/{$ali->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ali removed.');

        $this->assertDatabaseMissing('requesters', ['id' => $ali->id]);
        $this->assertDatabaseMissing('company_requester', ['requester_id' => $ali->id]);
        $this->assertSame('Ali', $request->fresh()->requested_by_name);
        Event::assertDispatched(RequesterDeleted::class);
    }

    /** Removing a company drops it from everyone's mapping, not the people. */
    public function test_deleting_a_company_unmaps_it(): void
    {
        $ali = Requester::create(['name' => 'Ali']);
        $ali->companies()->sync([$this->miknas->id, $this->steelTech->id]);

        $this->steelTech->delete();

        $this->assertSame([$this->miknas->id], $ali->fresh()->companies->pluck('id')->all());
    }
}
