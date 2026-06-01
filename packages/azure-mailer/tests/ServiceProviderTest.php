<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Tests;

use Illuminate\Mail\MailManager;
use PromoSeven\AzureMailer\Transport\AzureTransport;

class ServiceProviderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mail.mailers.azure', [
            'transport'     => 'azure',
            'tenant_id'     => 'test-tenant',
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
            'from_address'  => 'sender@example.com',
        ]);
    }

    public function test_azure_transport_is_registered_with_mail_manager(): void
    {
        $manager   = $this->app->make(MailManager::class);
        $transport = $manager->mailer('azure')->getSymfonyTransport();

        $this->assertInstanceOf(AzureTransport::class, $transport);
        $this->assertSame('azure', (string) $transport);
    }
}
