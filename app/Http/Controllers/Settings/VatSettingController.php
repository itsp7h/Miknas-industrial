<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class VatSettingController extends Controller
{
    public function index()
    {
        $vatRate = Setting::get('vat_rate', '0');
        return view('settings.vat', compact('vatRate'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::set('vat_rate', (string) $validated['vat_rate']);

        return response()->json(['message' => 'VAT rate saved.', 'vat_rate' => $validated['vat_rate']]);
    }
}
