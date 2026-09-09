<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unread(Request $request)
    {
        $notifications = $request->user()->unreadNotifications()->latest()->take(10)->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'title' => 'New Quote Received', // static, matches Task 3's precedent — only database-channel notification type today (QuoteReceived)
                'body' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null,
                'created_at' => $n->created_at,
            ];
        });

        return response()->json(['notifications' => $notifications]);
    }

    /** Backs the dropdown's "Mark all read" control, which the Blade topbar also had. */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['marked' => true]);
    }
}
