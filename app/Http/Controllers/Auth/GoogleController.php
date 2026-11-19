<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SocialLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) (config('services.google.client_id') && config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled or failed. Please try again.']);
        }

        if (! $google->getEmail()) {
            return redirect()->route('login')->withErrors(['email' => 'Google did not share an email address for this account.']);
        }

        $user = SocialLogin::resolve('google_id', (string) $google->getId(), $google->getEmail(),
            $google->getName() ?: $google->getNickname(), $google->getAvatar());

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('game'));
    }
}
