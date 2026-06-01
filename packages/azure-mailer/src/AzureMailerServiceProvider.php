<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use PromoSeven\AzureMailer\Graph\GraphClient;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\AzureMailer\Transport\AzureTransport;

class AzureMailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/azure-mailer.php', 'azure-mailer');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/azure-mailer.php' => config_path('azure-mailer.php'),
        ], 'azure-mailer-config');

        $this->callAfterResolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('azure', function (array $config) {
                $merged = array_merge(config('azure-mailer', []), $config);

                return new AzureTransport(
                    new GraphClient(new TokenManager($merged), $merged),
                    $merged
                );
            });
        });
    }
}
