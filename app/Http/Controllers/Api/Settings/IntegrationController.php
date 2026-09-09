<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PromoSeven\UltraMessage\Facades\UltraMessage;
use PromoSeven\UltraMessage\UltraMessageException;

class IntegrationController extends Controller
{
    /**
     * The token and webhook secret are deliberately absent: the Blade page put
     * the live UltraMSG token into an input's `value`, so it reached the browser
     * (and anyone reading over a shoulder or a saved page) on every visit. The
     * page only needs to know whether one is already stored — a blank field on
     * save means "leave it alone".
     */
    public function whatsapp(): JsonResponse
    {
        return response()->json([
            'enabled' => Setting::get('ultramsg_enabled', '0') === '1',
            'instance_id' => Setting::get('ultramsg_instance_id', ''),
            'webhook_path' => Setting::get('ultramsg_webhook_path', 'ultra-message/webhook'),
            'token_set' => Setting::get('ultramsg_token', '') !== '',
            'webhook_secret_set' => Setting::get('ultramsg_webhook_secret', '') !== '',
            // The page shows the full webhook URL under the path field.
            'base_url' => rtrim(url('/'), '/'),
        ]);
    }

    public function updateWhatsapp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'instance_id' => ['required', 'string', 'max:100'],
            'webhook_path' => ['required', 'string', 'max:100'],
            // Blank means unchanged, so neither is required once one is stored.
            'token' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'clear_webhook_secret' => ['boolean'],
        ]);

        if (($data['token'] ?? '') === '' && Setting::get('ultramsg_token', '') === '') {
            return response()->json([
                'message' => 'An API token is required the first time these settings are saved.',
                'errors' => ['token' => ['An API token is required.']],
            ], 422);
        }

        Setting::set('ultramsg_enabled', $request->boolean('enabled') ? '1' : '0');
        Setting::set('ultramsg_instance_id', $data['instance_id']);
        Setting::set('ultramsg_webhook_path', $data['webhook_path']);

        if (($data['token'] ?? '') !== '') {
            Setting::set('ultramsg_token', $data['token']);
        }

        // The secret is optional, so there has to be a way to remove one.
        if ($request->boolean('clear_webhook_secret')) {
            Setting::set('ultramsg_webhook_secret', '');
        } elseif (($data['webhook_secret'] ?? '') !== '') {
            Setting::set('ultramsg_webhook_secret', $data['webhook_secret']);
        }

        return $this->whatsapp();
    }

    public function testConnection(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'status' => UltraMessage::getInstanceStatus()]);
        } catch (UltraMessageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function sendTestMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:30'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        try {
            UltraMessage::sendText($data['to'], $data['body']);

            return response()->json(['success' => true]);
        } catch (UltraMessageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
