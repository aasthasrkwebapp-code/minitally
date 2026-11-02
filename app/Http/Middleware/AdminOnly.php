<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keeps staff logins out of everything except the daily entries list. */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isStaff()) {
            return redirect()->route('entries.index');
        }

        return $next($request);
    }
}
