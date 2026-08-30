<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Graph;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PromoSeven\AzureMailer\Exceptions\GraphApiException;

class GraphClient
{
    public function __construct(
        private readonly TokenManager $tokenManager,
        private readonly array $config
    ) {}

    public function send(array $payload): void
    {
        $url = $this->endpoint();

        $response = Http::withToken($this->tokenManager->getToken())
            ->timeout($this->config['timeout'] ?? 30)
            ->post($url, $payload);

        if ($response->status() === 401) {
            Log::warning('azure-mailer: 401 received, retrying with fresh token');
            $this->tokenManager->invalidate();

            $response = Http::withToken($this->tokenManager->getToken())
                ->timeout($this->config['timeout'] ?? 30)
                ->post($url, $payload);
        }

        if (! $response->successful()) {
            $error = $response->json('error', []);
            throw GraphApiException::fromResponse(
                $error['code'] ?? (string) $response->status(),
                $error['message'] ?? 'Unknown error'
            );
        }
    }

    private function endpoint(): string
    {
        $version = $this->config['graph_api_version'] ?? 'v1.0';
        $from = $this->config['from_address'];

        return "https://graph.microsoft.com/{$version}/users/{$from}/sendMail";
    }
}
