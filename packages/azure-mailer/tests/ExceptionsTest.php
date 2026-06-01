<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Tests;

use PromoSeven\AzureMailer\Exceptions\AuthenticationException;
use PromoSeven\AzureMailer\Exceptions\GraphApiException;

class ExceptionsTest extends \PHPUnit\Framework\TestCase
{
    public function test_authentication_exception_formats_message(): void
    {
        $e = AuthenticationException::fromResponse('invalid_client', 'The client secret is incorrect.');

        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('invalid_client', $e->getMessage());
        $this->assertStringContainsString('The client secret is incorrect.', $e->getMessage());
    }

    public function test_graph_api_exception_formats_message(): void
    {
        $e = GraphApiException::fromResponse('ErrorItemNotFound', 'The specified object was not found.');

        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('ErrorItemNotFound', $e->getMessage());
        $this->assertStringContainsString('The specified object was not found.', $e->getMessage());
    }
}
