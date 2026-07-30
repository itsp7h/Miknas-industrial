<?php

namespace App\Providers;

use App\Models\MailAccount;
use App\Models\Setting;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PromoSeven\UltraMessage\Facades\UltraMessage;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('Admin') ? true : null;
        });

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

        $this->callAfterResolving(MailManager::class, function (MailManager $manager) {
            try {
                foreach (MailAccount::all() as $account) {
                    config(["mail.mailers.{$account->name}" => ['transport' => $account->name]]);
                    $manager->extend($account->name, fn () => $account->buildTransport());
                }
            } catch (\Exception) {
                // DB not ready on fresh install — skip silently
            }
        });
    }
}
