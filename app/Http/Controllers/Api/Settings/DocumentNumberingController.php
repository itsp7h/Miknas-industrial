<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Company;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Each company's letters in its document numbers — the ST in ST-LPO-26-0001
 * and ST-MPR-26-0001.
 *
 * One code per company, used by both documents: it identifies the company,
 * not the paperwork. Only the code is editable; the rest of the shape is
 * fixed, and each document keeps its own sequence per company per year.
 */
class DocumentNumberingController extends Controller
{
    public function index(DocumentNumberService $numbers)
    {
        return response()->json(['data' => $this->companies($numbers)]);
    }

    public function update(Request $request, DocumentNumberService $numbers)
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
            'message' => 'Document numbering saved.',
            'data' => $this->companies($numbers),
        ]);
    }

    /** Every company, with what its next number of each document would read. */
    private function companies(DocumentNumberService $numbers)
    {
        return Company::orderBy('name')->get(['id', 'name', 'lpo_code', 'mpr_code', 'is_active'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'is_active' => $company->is_active,
                'lpo_code' => $company->lpo_code,
                // The company's own word for a material request, resolved
                // rather than raw, so the page never has to know the default.
                'mpr_code' => $numbers->token($company, DocumentNumberService::MPR),
                // What the next ones would actually read, rather than an
                // example: the same calls the generators make.
                'next_number' => $numbers->next($company, DocumentNumberService::LPO),
                'next_mpr_number' => $numbers->next($company, DocumentNumberService::MPR),
            ])->values();
    }
}
