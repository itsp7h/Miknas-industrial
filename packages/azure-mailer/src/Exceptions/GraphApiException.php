<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Exceptions;

class GraphApiException extends \RuntimeException
{
    public static function fromResponse(string $code, string $message): self
    {
        return new self("Graph API error: [{$code}] {$message}");
    }
}
