<?php

namespace PromoSeven\UltraMessage;

use Illuminate\Support\Facades\Http;

class UltraMessageClient
{
    private const BASE_URL = 'https://api.ultramsg.com';

    private string $instanceId;

    private string $token;

    private int $timeout;

    private bool $enabled;

    public function __construct(array $config)
    {
        $this->instanceId = $config['instance_id'] ?? '';
        $this->token = $config['token'] ?? '';
        $this->timeout = $config['timeout'] ?? 30;
        $this->enabled = $config['enabled'] ?? true;
    }

    protected function post(string $endpoint, array $data): array
    {
        if (! $this->enabled) {
            return [];
        }

        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post(self::BASE_URL."/{$this->instanceId}/{$endpoint}", array_merge($data, [
                'token' => $this->token,
            ]));

        if ($response->failed()) {
            throw new UltraMessageException("UltraMSG HTTP error: {$response->status()}");
        }

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            throw new UltraMessageException($body['error']);
        }

        return $body;
    }

    protected function get(string $endpoint, array $query = []): array
    {
        if (! $this->enabled) {
            return [];
        }

        $response = Http::timeout($this->timeout)
            ->get(self::BASE_URL."/{$this->instanceId}/{$endpoint}", array_merge($query, [
                'token' => $this->token,
            ]));

        if ($response->failed()) {
            throw new UltraMessageException("UltraMSG HTTP error: {$response->status()}");
        }

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            throw new UltraMessageException($body['error']);
        }

        return $body;
    }

    public function sendText(string $to, string $message, ?string $replyId = null): array
    {
        $data = ['to' => $to, 'body' => $message];
        if ($replyId !== null) {
            $data['quoted_id'] = $replyId;
        }

        return $this->post('messages/chat', $data);
    }

    public function sendImage(string $to, string $imageUrl, string $caption = ''): array
    {
        return $this->post('messages/image', [
            'to' => $to,
            'image' => $imageUrl,
            'caption' => $caption,
        ]);
    }

    public function sendDocument(string $to, string $fileUrl, string $filename, string $caption = ''): array
    {
        return $this->post('messages/document', [
            'to' => $to,
            'document' => $fileUrl,
            'filename' => $filename,
            'caption' => $caption,
        ]);
    }

    public function sendAudio(string $to, string $audioUrl): array
    {
        return $this->post('messages/audio', [
            'to' => $to,
            'audio' => $audioUrl,
        ]);
    }

    public function sendVoice(string $to, string $audioUrl): array
    {
        return $this->post('messages/voice', [
            'to' => $to,
            'audio' => $audioUrl,
        ]);
    }

    public function sendVideo(string $to, string $videoUrl, string $caption = ''): array
    {
        return $this->post('messages/video', [
            'to' => $to,
            'video' => $videoUrl,
            'caption' => $caption,
        ]);
    }

    public function sendSticker(string $to, string $stickerUrl): array
    {
        return $this->post('messages/sticker', [
            'to' => $to,
            'sticker' => $stickerUrl,
        ]);
    }

    public function sendContact(string $to, string $contactId): array
    {
        return $this->post('messages/contact', [
            'to' => $to,
            'contact' => $contactId,
        ]);
    }

    public function sendLocation(string $to, float $lat, float $lng, string $address = ''): array
    {
        return $this->post('messages/location', [
            'to' => $to,
            'lat' => $lat,
            'lng' => $lng,
            'address' => $address,
        ]);
    }

    public function sendReaction(string $to, string $messageId, string $emoji): array
    {
        return $this->post('messages/reaction', [
            'to' => $to,
            'msgId' => $messageId,
            'emoji' => $emoji,
        ]);
    }

    public function deleteMessage(string $messageId): array
    {
        return $this->post('messages/delete', [
            'msgId' => $messageId,
        ]);
    }

    public function getInstanceStatus(): array
    {
        return $this->get('instance/status');
    }

    public function getChats(): array
    {
        return $this->get('chats/');
    }

    public function getContacts(): array
    {
        return $this->get('contacts/');
    }

    public function getGroups(): array
    {
        return $this->get('groups/');
    }
}
