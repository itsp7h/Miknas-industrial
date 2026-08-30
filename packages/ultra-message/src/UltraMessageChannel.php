<?php

namespace PromoSeven\UltraMessage;

use Illuminate\Notifications\Notification;

class UltraMessageChannel
{
    public function __construct(private UltraMessageClient $client) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toUltraMessage')) {
            return;
        }

        /** @var UltraMessageMessage $message */
        $message = $notification->toUltraMessage($notifiable);

        $to = $message->to ?: $notifiable->routeNotificationFor('ultra_message', $notification);

        if (! $to) {
            return;
        }

        match ($message->type) {
            'text' => $this->client->sendText($to, $message->payload['body'], $message->payload['quoted_id'] ?? null),
            'image' => $this->client->sendImage($to, $message->payload['image'], $message->payload['caption'] ?? ''),
            'document' => $this->client->sendDocument($to, $message->payload['document'], $message->payload['filename'], $message->payload['caption'] ?? ''),
            'audio' => $this->client->sendAudio($to, $message->payload['audio']),
            'voice' => $this->client->sendVoice($to, $message->payload['audio']),
            'video' => $this->client->sendVideo($to, $message->payload['video'], $message->payload['caption'] ?? ''),
            'sticker' => $this->client->sendSticker($to, $message->payload['sticker']),
            'contact' => $this->client->sendContact($to, $message->payload['contact']),
            'location' => $this->client->sendLocation($to, $message->payload['lat'], $message->payload['lng'], $message->payload['address'] ?? ''),
            default => throw new UltraMessageException("Unknown message type: {$message->type}"),
        };
    }
}
