<?php

return [
    'instance_id'    => env('ULTRAMSG_INSTANCE_ID'),
    'token'          => env('ULTRAMSG_TOKEN'),
    'webhook_secret' => env('ULTRAMSG_WEBHOOK_SECRET', null),
    'webhook_path'   => env('ULTRAMSG_WEBHOOK_PATH', 'ultra-message/webhook'),
    'timeout'        => env('ULTRAMSG_TIMEOUT', 30),
    'enabled'        => env('ULTRAMSG_ENABLED', true),
];
