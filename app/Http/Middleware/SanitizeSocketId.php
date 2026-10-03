<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drops an X-Socket-ID header that is not a socket id.
 *
 * `broadcast(...)->toOthers()` reads that header to exclude the sender's own
 * connection. When the websocket has not finished connecting, the browser can
 * send the literal string "undefined", and the broadcasting client then rejects
 * the whole call with "Invalid socket ID undefined" — so the event is never
 * published at all. In production that showed up as messages that saved but
 * never arrived, and call signals that went nowhere, with only a warning in the
 * log.
 *
 * Removing the bad header turns toOthers() back into an ordinary broadcast:
 * everyone receives it, including the sender, whose UI already shows it.
 */
class SanitizeSocketId
{
    /** Pusher/Reverb socket ids look like "123456.7891011". */
    private const PATTERN = '/^\d+\.\d+$/';

    public function handle(Request $request, Closure $next): Response
    {
        $socketId = $request->header('X-Socket-ID');

        if ($socketId !== null && ! preg_match(self::PATTERN, $socketId)) {
            $request->headers->remove('X-Socket-ID');
        }

        return $next($request);
    }
}
