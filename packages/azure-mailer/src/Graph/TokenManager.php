<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PromoSeven\AzureMailer\Exceptions\AuthenticationException;

class TokenManager
{
    public function __construct(private readonly array $config) {}

    public function getToken(): string
    {
        $key = $this->cacheKey();

        if ($token = Cache::get($key)) {
            return $token;
        }

        return $this->fetchAndCache($key);
    }

    public function invalidate(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function fetchAndCache(string $key): string
    {
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$this->config['tenant_id']}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (! $response->successful()) {
            $body = $response->json();
            throw AuthenticationException::fromResponse(
                $body['error'] ?? 'unknown_error',
                $body['error_description'] ?? 'No description provided'
            );
        }

        $body = $response->json();
        $ttl  = max(1, ($body['expires_in'] ?? 3600) - 60);

        Cache::put($key, $body['access_token'], $ttl);

        return $body['access_token'];
    }

    private function cacheKey(): string
    {
        return 'azure_mailer_token_' . $this->config['client_id'];
    }
}
