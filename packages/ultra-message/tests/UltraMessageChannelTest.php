<?php

namespace PromoSeven\UltraMessage\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageClient;
use PromoSeven\UltraMessage\UltraMessageMessage;

class UltraMessageChannelTest extends TestCase
{
    public function test_channel_calls_send_text_for_text_message(): void
    {
        Http::fake(['api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200)]);

        $client  = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
        $channel = new UltraMessageChannel($client);

        $notifiable = new class {
            public string $whatsapp_number = '+971501234567';
            public function routeNotificationFor(string $channel, $notification = null): string
            {
                return $this->whatsapp_number;
            }
        };

        $notification = new class extends Notification {
            public function toUltraMessage($notifiable): UltraMessageMessage
            {
                return UltraMessageMessage::text('Test message');
            }
        };

        $channel->send($notifiable, $notification);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'messages/chat')
                && $request['body'] === 'Test message'
                && $request['to'] === '+971501234567';
        });
    }

    public function test_channel_uses_message_to_over_notifiable_route(): void
    {
        Http::fake(['api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200)]);

        $client  = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
        $channel = new UltraMessageChannel($client);

        $notifiable = new class {
            public function routeNotificationFor(string $channel, $notification = null): string
            {
                return '+9710000000';
            }
        };

        $notification = new class extends Notification {
            public function toUltraMessage($notifiable): UltraMessageMessage
            {
                return UltraMessageMessage::text('Override test')->to('+971999999');
            }
        };

        $channel->send($notifiable, $notification);

        Http::assertSent(fn($r) => $r['to'] === '+971999999');
    }

    public function test_channel_skips_when_no_recipient(): void
    {
        Http::fake();

        $client  = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
        $channel = new UltraMessageChannel($client);

        $notifiable = new class {
            public function routeNotificationFor(string $channel, $notification = null): ?string
            {
                return null;
            }
        };

        $notification = new class extends Notification {
            public function toUltraMessage($notifiable): UltraMessageMessage
            {
                return UltraMessageMessage::text('No recipient');
            }
        };

        $channel->send($notifiable, $notification);

        Http::assertNothingSent();
    }
}
