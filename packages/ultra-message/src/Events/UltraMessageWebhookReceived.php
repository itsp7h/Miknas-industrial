<?php

namespace PromoSeven\UltraMessage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UltraMessageWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly array $payload) {}
}
