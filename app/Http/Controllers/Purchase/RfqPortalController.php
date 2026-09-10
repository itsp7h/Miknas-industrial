<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\RfqInvitation;

/**
 * The public quote portal's host page — the Blade that mounts React and
 * nothing else. Reading the invitation and recording the quote belong to
 * Api\Purchase\RfqPortalController; all this route decides is whether the
 * token is one we issued, so an unknown link still 404s at the door rather
 * than painting a shell that then reports the same thing.
 *
 * It stays Blade for the same reason the login page does: a supplier is not
 * authenticated, so the portal cannot be a route inside the /app shell.
 */
class RfqPortalController extends Controller
{
    public function show(string $token)
    {
        RfqInvitation::where('token', $token)->firstOrFail();

        return view('rfq.portal', compact('token'));
    }
}
