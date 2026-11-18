<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Private-channel authorisation for the app. Laravel's own /broadcasting/auth
 * runs the web (session) middleware, which a phone carrying a Bearer token
 * never passes. Same rules (routes/channels.php), token-authenticated.
 */
class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request)
    {
        return Broadcast::auth($request);
    }
}
