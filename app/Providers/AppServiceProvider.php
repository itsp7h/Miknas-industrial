<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;
use PromoSeven\UltraMessage\Facades\UltraMessage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        UltraMessage::configUsing(function () {
            return [
                'instance_id'    => Setting::get('ultramsg_instance_id', config('ultra-message.instance_id')),
                'token'          => Setting::get('ultramsg_token', config('ultra-message.token')),
                'webhook_secret' => Setting::get('ultramsg_webhook_secret', config('ultra-message.webhook_secret')),
                'webhook_path'   => Setting::get('ultramsg_webhook_path', config('ultra-message.webhook_path', 'ultra-message/webhook')),
                'timeout'        => config('ultra-message.timeout', 30),
                'enabled'        => (bool) Setting::get('ultramsg_enabled', config('ultra-message.enabled', true)),
            ];
        });
    }
}
