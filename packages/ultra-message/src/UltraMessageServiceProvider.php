<?php

namespace PromoSeven\UltraMessage;

use Illuminate\Support\ServiceProvider;

class UltraMessageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ultra-message.php', 'ultra-message');

        $this->app->singleton('ultra-message.config-resolver', fn () => null);

        $this->app->bind(UltraMessageClient::class, function ($app) {
            $resolver = $app->make('ultra-message.config-resolver');
            $config = $resolver ? call_user_func($resolver) : config('ultra-message');

            return new UltraMessageClient($config);
        });

        $this->app->bind(UltraMessageChannel::class, function ($app) {
            return new UltraMessageChannel($app->make(UltraMessageClient::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ultra-message.php' => config_path('ultra-message.php'),
            ], 'ultra-message-config');
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
    }
}
