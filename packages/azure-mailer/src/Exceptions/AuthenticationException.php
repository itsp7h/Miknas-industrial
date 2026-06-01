<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Exceptions;

class AuthenticationException extends \RuntimeException
{
    public static function fromResponse(string $error, string $description): self
    {
        return new self("Azure AD authentication failed: [{$error}] {$description}");
    }
}
