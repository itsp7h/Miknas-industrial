<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\UltraMessage\Facades\UltraMessage;
use PromoSeven\UltraMessage\UltraMessageException;

class SettingsController extends Controller
{
    public function integrations(): View
    {
        $whatsappSettings = [
            'enabled'        => Setting::get('ultramsg_enabled', false),
            'instance_id'    => Setting::get('ultramsg_instance_id', ''),
            'token'          => Setting::get('ultramsg_token', ''),
            'webhook_secret' => Setting::get('ultramsg_webhook_secret', ''),
            'webhook_path'   => Setting::get('ultramsg_webhook_path', 'ultra-message/webhook'),
        ];

        $azureSettings = [
            'enabled'       => Setting::get('azure_mail_enabled', false),
            'tenant_id'     => Setting::get('azure_mail_tenant_id', ''),
            'client_id'     => Setting::get('azure_mail_client_id', ''),
            'client_secret' => Setting::get('azure_mail_client_secret', ''),
            'from_address'  => Setting::get('azure_mail_from_address', ''),
        ];

        return view('settings.integrations', compact('whatsappSettings', 'azureSettings'));
    }

    public function updateWhatsapp(Request $request): JsonResponse
    {
        $request->validate([
            'instance_id'    => ['required', 'string', 'max:100'],
            'token'          => ['required', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'webhook_path'   => ['required', 'string', 'max:100'],
        ]);

        Setting::set('ultramsg_enabled', $request->input('enabled') === '1' ? '1' : '0');
        Setting::set('ultramsg_instance_id', $request->instance_id);
        Setting::set('ultramsg_token', $request->token);
        Setting::set('ultramsg_webhook_secret', $request->webhook_secret ?? '');
        Setting::set('ultramsg_webhook_path', $request->webhook_path);

        return response()->json(['success' => true]);
    }

    public function updateAzureMail(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id'     => ['required', 'string', 'max:100'],
            'client_id'     => ['required', 'string', 'max:100'],
            'client_secret' => ['required', 'string', 'max:500'],
            'from_address'  => ['required', 'email', 'max:255'],
        ]);

        Setting::set('azure_mail_enabled', $request->input('enabled') === '1' ? '1' : '0');
        Setting::set('azure_mail_tenant_id', $request->tenant_id);
        Setting::set('azure_mail_client_id', $request->client_id);
        Setting::set('azure_mail_client_secret', $request->client_secret);
        Setting::set('azure_mail_from_address', $request->from_address);

        return response()->json(['success' => true]);
    }

    public function testAzureMailConnection(): JsonResponse
    {
        try {
            $config = [
                'tenant_id'     => Setting::get('azure_mail_tenant_id', ''),
                'client_id'     => Setting::get('azure_mail_client_id', ''),
                'client_secret' => Setting::get('azure_mail_client_secret', ''),
            ];
            $tokenManager = new TokenManager($config);
            $tokenManager->getToken();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $request->validate([
            'to'      => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
        ]);

        try {
            $to      = $request->to;
            $subject = $request->subject;
            Mail::mailer('azure')->raw(
                'This is a test email from SteelERP.',
                function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                }
            );
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
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

    public function sendTestMessage(Request $request): JsonResponse
    {
        $request->validate([
            'to'   => ['required', 'string', 'max:30'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        try {
            UltraMessage::sendText($request->to, $request->body);
            return response()->json(['success' => true]);
        } catch (UltraMessageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
