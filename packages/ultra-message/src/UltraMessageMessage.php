<?php

namespace PromoSeven\UltraMessage;

class UltraMessageMessage
{
    public string $type;
    public string $to = '';
    public array $payload = [];

    private function __construct(string $type, array $payload)
    {
        $this->type    = $type;
        $this->payload = $payload;
    }

    public static function text(string $message, ?string $replyId = null): self
    {
        return new self('text', ['body' => $message, 'quoted_id' => $replyId]);
    }

    public static function image(string $url, string $caption = ''): self
    {
        return new self('image', ['image' => $url, 'caption' => $caption]);
    }

    public static function document(string $url, string $filename, string $caption = ''): self
    {
        return new self('document', ['document' => $url, 'filename' => $filename, 'caption' => $caption]);
    }

    public static function audio(string $url): self
    {
        return new self('audio', ['audio' => $url]);
    }

    public static function voice(string $url): self
    {
        return new self('voice', ['audio' => $url]);
    }

    public static function video(string $url, string $caption = ''): self
    {
        return new self('video', ['video' => $url, 'caption' => $caption]);
    }

    public static function sticker(string $url): self
    {
        return new self('sticker', ['sticker' => $url]);
    }

    public static function contact(string $contactId): self
    {
        return new self('contact', ['contact' => $contactId]);
    }

    public static function location(float $lat, float $lng, string $address = ''): self
    {
        return new self('location', ['lat' => $lat, 'lng' => $lng, 'address' => $address]);
    }

    public function to(string $number): self
    {
        $this->to = $number;
        return $this;
    }
}
