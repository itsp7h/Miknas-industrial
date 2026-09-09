<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VatController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['vat_rate' => (float) Setting::get('vat_rate', '0')]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        // Stored as a string, which is what Setting holds and what the sales
        // invoice controller reads back.
        Setting::set('vat_rate', (string) $validated['vat_rate']);

        return response()->json([
            'message' => 'VAT rate saved.',
            'vat_rate' => (float) $validated['vat_rate'],
        ]);
    }
}
