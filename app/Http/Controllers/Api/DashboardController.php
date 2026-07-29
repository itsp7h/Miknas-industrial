<?php

namespace App\Http\Controllers\Api;

use App\Events\DashboardPinged;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function ping(Request $request)
    {
        event(new DashboardPinged($request->user()->id, 'Live update check'));

        return response()->noContent();
    }
}
