<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

Broadcast::channel('purchase', function (User $user) {
    return true; // any authenticated user; matches the /app/purchase/suppliers route (auth+verified only, no extra gate)
});

Broadcast::channel('inventory', function (User $user) {
    return true; // any authenticated user, matching the /app/inventory/* routes (auth+verified only)
});
