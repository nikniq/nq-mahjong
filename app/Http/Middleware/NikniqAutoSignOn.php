<?php

namespace App\Http\Middleware;

use App\Support\Nikniq;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guest's page view takes a silent detour through nikniq.com (at most every
 * RECHECK seconds): if they are logged in there, they come back signed in.
 * Players who log out here are left alone for the rest of the session.
 */
class NikniqAutoSignOn
{
    private const RECHECK = 600;

    private const SKIP = ['auth/*', 'login', 'register', 'logout', 'up', 'robots.txt', 'scores'];

    public function handle(Request $request, Closure $next): Response
    {
        if (Nikniq::enabled()
            && config('services.nikniq.auto_sign_on')
            && $request->isMethod('GET')
            && ! $request->user()
            && ! $request->ajax() && ! $request->expectsJson() && ! $request->prefetch()
            && ! $request->session()->get('nikniq_optout')
            && now()->timestamp - (int) $request->session()->get('nikniq_checked', 0) >= self::RECHECK
            && ! $request->is(...self::SKIP)
            && ! preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget|lighthouse/i', (string) $request->userAgent())) {
            $request->session()->put('nikniq_checked', now()->timestamp);
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('auth.nikniq', ['silent' => 1]);
        }

        return $next($request);
    }
}
