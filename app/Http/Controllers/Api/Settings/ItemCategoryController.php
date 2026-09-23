<?php

namespace App\Http\Controllers\Api\Settings;

use App\Events\ItemCategoryDeleted;
use App\Events\ItemCategorySaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\ItemCategoryResource;
use App\Models\ItemCategory;
use Illuminate\Http\Request;

class ItemCategoryController extends Controller
{
    public function index()
    {
        $categories = ItemCategory::withCount('items')->ordered()->get();

        return ItemCategoryResource::collection($categories)->additional([
            'meta' => [
                'parent_types' => collect(ItemCategory::PARENT_LABELS)
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $category = ItemCategory::create($this->validated($request));

        event(new ItemCategorySaved($category));

        return (new ItemCategoryResource($category->loadCount('items')))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, ItemCategory $itemCategory)
    {
        $itemCategory->update($this->validated($request, $itemCategory));

        event(new ItemCategorySaved($itemCategory));

        return new ItemCategoryResource($itemCategory->loadCount('items'));
    }

    /**
     * items.item_category_id is `restrictOnDelete`, so deleting a section that
     * still holds items fails at the database. That would surface as a 500
     * carrying a raw QueryException — the shape of bug the item delete already
     * had — so it is refused here with a count and a reason instead.
     */
    public function destroy(ItemCategory $itemCategory)
    {
        $itemCount = $itemCategory->items()->count();

        abort_if(
            $itemCount > 0,
            422,
            "{$itemCount} item(s) are still in this section. Move them to another section first."
        );

        $id = $itemCategory->id;
        $itemCategory->delete();

        event(new ItemCategoryDeleted($id));

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    private function validated(Request $request, ?ItemCategory $existing = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255|unique:item_categories,name'.($existing ? ','.$existing->id : ''),
            'parent_type' => 'required|in:'.implode(',', array_keys(ItemCategory::PARENT_LABELS)),
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
