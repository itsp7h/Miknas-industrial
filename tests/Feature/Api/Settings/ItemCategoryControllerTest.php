<?php

namespace Tests\Feature\Api\Settings;

use App\Events\ItemCategoryDeleted;
use App\Events\ItemCategorySaved;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ItemCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    public function test_it_is_admin_only(): void
    {
        $this->getJson('/api/v1/settings/item-categories')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/item-categories')
            ->assertForbidden();
    }

    /**
     * The five sections the Forkoll sheet has always used are seeded by the
     * migration, so the dropdown is useful the moment the app is installed
     * rather than after someone types them in.
     */
    public function test_it_lists_the_seeded_sections_in_sheet_order(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/item-categories')
            ->assertOk();

        $this->assertSame(
            ['Chemical Materials', 'Natural Pigments (Colors)', 'Bulk', 'Forkoll Bags', 'Others'],
            array_column($response->json('data'), 'name')
        );
        $this->assertSame(
            'Raw Materials / Chemical Materials',
            $response->json('data.0.path')
        );
    }

    public function test_it_creates_a_category_and_broadcasts(): void
    {
        Event::fake([ItemCategorySaved::class]);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/item-categories', ['name' => 'Packaging', 'parent_type' => 'raw_material'])
            ->assertCreated()
            ->assertJsonPath('data.path', 'Raw Materials / Packaging');

        Event::assertDispatched(ItemCategorySaved::class);
    }

    public function test_it_rejects_a_duplicate_name_and_an_unknown_parent(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/item-categories', ['name' => 'Bulk', 'parent_type' => 'raw_material'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/item-categories', ['name' => 'New', 'parent_type' => 'consumable'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_type']);
    }

    /** Renaming a section is one row, which is the reason it is a table. */
    public function test_renaming_a_section_does_not_touch_its_items(): void
    {
        $section = ItemCategory::where('name', 'Bulk')->firstOrFail();
        $item = Item::create([
            'item_code' => 'ITEM-1', 'item_name' => 'Silica Sand', 'category' => 'raw_material',
            'item_category_id' => $section->id, 'unit_of_measure' => 'KG',
        ]);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/settings/item-categories/{$section->id}", [
                'name' => 'Bulk Materials', 'parent_type' => 'raw_material',
            ])
            ->assertOk()
            ->assertJsonPath('data.path', 'Raw Materials / Bulk Materials');

        $this->assertSame($section->id, $item->fresh()->item_category_id);
        $this->assertSame('Raw Materials / Bulk Materials', $item->fresh()->categoryPath());
    }

    /**
     * items.item_category_id is restrictOnDelete, so without this guard the
     * delete would fail at the database and answer with a 500 carrying a raw
     * QueryException — the same shape of bug the item delete had.
     */
    public function test_it_refuses_to_delete_a_section_that_still_holds_items(): void
    {
        $section = ItemCategory::where('name', 'Chemical Materials')->firstOrFail();
        Item::create([
            'item_code' => 'ITEM-1', 'item_name' => 'Pentaproof 20 P', 'category' => 'raw_material',
            'item_category_id' => $section->id, 'unit_of_measure' => 'KG',
        ]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/settings/item-categories/{$section->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', '1 item(s) are still in this section. Move them to another section first.');

        $this->assertDatabaseHas('item_categories', ['id' => $section->id]);
    }

    public function test_it_deletes_an_empty_section_and_broadcasts(): void
    {
        Event::fake([ItemCategoryDeleted::class]);
        $section = ItemCategory::where('name', 'Others')->firstOrFail();

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/settings/item-categories/{$section->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('item_categories', ['id' => $section->id]);
        Event::assertDispatched(ItemCategoryDeleted::class);
    }
}
