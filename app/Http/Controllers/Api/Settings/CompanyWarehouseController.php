<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Company;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Which warehouse each company receives its purchases into.
 *
 * A material request names its company, so by the time its goods arrive the
 * yard is already implied — Miknas Industrial receives at Askar, Steel Tech at
 * Hidd. Setting it here is what makes the goods receipt form stop asking.
 *
 * Unlike the document code, two companies may share a warehouse: one yard
 * serving two companies is a real arrangement, whereas two companies sharing a
 * document code would share a sequence and mint indistinguishable numbers.
 */
class CompanyWarehouseController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => $this->companies(),
            // Inactive warehouses are offered too, so that a company already
            // pointing at one does not silently lose its link when the yard is
            // deactivated; the list marks them.
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'links' => ['required', 'array'],
            'links.*.id' => ['required', Rule::exists('settings_companies', 'id')],
            // Null is the meaningful empty value, not a missing key: it is how
            // a company is unlinked, and how one stays unlinked until someone
            // decides where its goods land.
            'links.*.warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')],
        ], [
            'links.*.warehouse_id.exists' => 'That warehouse no longer exists.',
        ]);

        foreach ($validated['links'] as $link) {
            Company::where('id', $link['id'])->update(['warehouse_id' => $link['warehouse_id'] ?? null]);
        }

        return response()->json([
            'message' => 'Company warehouses saved.',
            'data' => $this->companies(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function companies()
    {
        return Company::with('warehouse')->orderBy('name')->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'is_active' => $company->is_active,
                'warehouse_id' => $company->warehouse_id,
                'warehouse_name' => $company->warehouse?->name,
            ])->values();
    }
}
