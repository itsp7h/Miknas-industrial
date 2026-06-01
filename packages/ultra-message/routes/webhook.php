<?php

use Illuminate\Support\Facades\Route;
use PromoSeven\UltraMessage\Http\Controllers\WebhookController;

Route::post(config('ultra-message.webhook_path', 'ultra-message/webhook'), [WebhookController::class, 'handle'])
    ->name('ultra-message.webhook');
