<?php

namespace PromoSeven\UltraMessage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PromoSeven\UltraMessage\Events\UltraMessageWebhookReceived;

class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('ultra-message.webhook_secret');

        if ($secret) {
            $signature = $request->header('X-Hub-Signature-256', '');
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

            if (!hash_equals($expected, $signature)) {
                abort(403, 'Invalid webhook signature.');
            }
        }

        event(new UltraMessageWebhookReceived($request->all()));

        return response('OK', 200);
    }
}
