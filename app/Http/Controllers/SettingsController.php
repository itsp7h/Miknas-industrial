<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PromoSeven\UltraMessage\Facades\UltraMessage;
use PromoSeven\UltraMessage\UltraMessageException;

class SettingsController extends Controller
{
    public function integrations(): View
    {
        $settings = [
            'enabled'        => Setting::get('ultramsg_enabled', false),
            'instance_id'    => Setting::get('ultramsg_instance_id', ''),
            'token'          => Setting::get('ultramsg_token', ''),
            'webhook_secret' => Setting::get('ultramsg_webhook_secret', ''),
            'webhook_path'   => Setting::get('ultramsg_webhook_path', 'ultra-message/webhook'),
        ];

        return view('settings.integrations', compact('settings'));
    }

    public function updateWhatsapp(Request $request): RedirectResponse
    {
        $request->validate([
            'instance_id'    => ['required', 'string', 'max:100'],
            'token'          => ['required', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'webhook_path'   => ['required', 'string', 'max:100'],
        ]);

        Setting::set('ultramsg_enabled', $request->boolean('enabled') ? '1' : '0');
        Setting::set('ultramsg_instance_id', $request->instance_id);
        Setting::set('ultramsg_token', $request->token);
        Setting::set('ultramsg_webhook_secret', $request->webhook_secret ?? '');
        Setting::set('ultramsg_webhook_path', $request->webhook_path);

        return redirect()->route('settings.integrations')->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsappConnection(): JsonResponse
    {
        try {
            $status = UltraMessage::getInstanceStatus();
            return response()->json(['success' => true, 'status' => $status]);
        } catch (UltraMessageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
