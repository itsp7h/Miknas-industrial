<?php

namespace PromoSeven\UltraMessage;

use PHPUnit\Framework\Assert;

class UltraMessageFake extends UltraMessageClient
{
    private array $sent = [];

    public function __construct()
    {
        // Skip parent constructor — no HTTP config needed in fake mode
    }

    protected function post(string $endpoint, array $data): array
    {
        $this->sent[] = ['endpoint' => $endpoint, 'data' => $data];
        return ['sent' => 'ok'];
    }

    protected function get(string $endpoint, array $query = []): array
    {
        return [];
    }

    public function assertSent(callable $callback): void
    {
        Assert::assertTrue(
            collect($this->sent)->contains($callback),
            'Expected UltraMessage was not sent.'
        );
    }

    public function assertNotSent(): void
    {
        Assert::assertEmpty($this->sent, 'Unexpected UltraMessage messages were sent.');
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent, "Expected {$count} messages sent, got " . count($this->sent));
    }

    public function getSent(): array
    {
        return $this->sent;
    }
}
