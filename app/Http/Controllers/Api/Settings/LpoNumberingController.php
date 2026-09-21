<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Company;
use App\Services\LpoNumberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Each company's letters in its LPO numbers — the ST in ST-LPO-26-0001.
 *
 * Only the code is editable. The rest of the shape is fixed: the document,
 * the two-digit year and a four-digit sequence that runs per company per year.
 */
class LpoNumberingController extends Controller
{
    public function index(LpoNumberService $numbers)
    {
        return response()->json([
            'data' => Company::orderBy('name')->get(['id', 'name', 'lpo_code', 'is_active'])
                ->map(fn (Company $company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'is_active' => $company->is_active,
                    'lpo_code' => $company->lpo_code,
                    // What the next one would actually read, rather than an
                    // example: it is the same call the generator makes.
                    'next_number' => $numbers->next($company),
                ])->values(),
        ]);
    }

    public function update(Request $request, LpoNumberService $numbers)
    {
        $validated = $request->validate([
            'codes' => ['required', 'array'],
            'codes.*.id' => ['required', Rule::exists('settings_companies', 'id')],
            // Letters and digits only, in the numbers themselves: a code with a
            // dash in it would make ST-X-LPO-26-0001 unparseable back into a
            // sequence, and the next number would restart at 0001.
            'codes.*.lpo_code' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
        ], [
            'codes.*.lpo_code.regex' => 'A code may only contain letters and numbers.',
            'codes.*.lpo_code.required' => 'Every company needs a code.',
        ]);

        $codes = collect($validated['codes']);

        // Two companies sharing a code would share a sequence, and their LPOs
        // would be indistinguishable from one another.
        $upper = $codes->map(fn ($row) => strtoupper($row['lpo_code']));
        if ($upper->unique()->count() !== $upper->count()) {
            return response()->json(['message' => 'Two companies cannot share the same code.'], 422);
        }

        foreach ($codes as $row) {
            Company::where('id', $row['id'])->update(['lpo_code' => strtoupper($row['lpo_code'])]);
        }

        return response()->json([
            'message' => 'LPO numbering saved.',
            'data' => Company::orderBy('name')->get(['id', 'name', 'lpo_code', 'is_active'])
                ->map(fn (Company $company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'is_active' => $company->is_active,
                    'lpo_code' => $company->lpo_code,
                    'next_number' => $numbers->next($company),
                ])->values(),
        ]);
    }
}
