<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Nikniq;
use App\Support\SocialLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sign in with a nikniq.com account through the portal's SSO (authorization code + PKCE).
 *
 * /auth/nikniq?silent=1 asks with prompt=none: a player already logged in on the
 * portal comes straight back signed in, and a guest comes back with
 * error=login_required and simply carries on as a guest.
 */
class NikniqController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(Nikniq::enabled(), 404);

        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put([
            'nikniq_state' => $state,
            'nikniq_verifier' => $verifier,
            'nikniq_silent' => $request->boolean('silent'),
        ]);

        $query = [
            'response_type' => 'code',
            'client_id' => config('services.nikniq.client_id'),
            'redirect_uri' => Nikniq::redirectUri(),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
        if ($request->boolean('silent')) {
            $query['prompt'] = 'none';
        }

        return redirect()->away(Nikniq::portal().'/sso/authorize?'.http_build_query($query));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(Nikniq::enabled(), 404);

        $silent = (bool) $request->session()->pull('nikniq_silent', false);
        $state = (string) $request->session()->pull('nikniq_state', '');
        $verifier = (string) $request->session()->pull('nikniq_verifier', '');
        $fail = fn (string $message) => $silent
            ? redirect()->intended(route('home'))
            : redirect()->route('login')->withErrors(['email' => $message]);

        if ($state === '' || ! hash_equals($state, (string) $request->query('state', ''))) {
            return $fail('nikniq.com sign-in expired. Please try again.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $fail('nikniq.com sign-in was cancelled. Please try again.');
        }

        try {
            $response = Http::asJson()->acceptJson()->timeout(10)->post(Nikniq::portal().'/api/sso/token', array_filter([
                'grant_type' => 'authorization_code',
                'client_id' => config('services.nikniq.client_id'),
                'client_secret' => config('services.nikniq.client_secret'),
                'code' => (string) $request->query('code'),
                'redirect_uri' => Nikniq::redirectUri(),
                'code_verifier' => $verifier,
            ]));
        } catch (\Throwable $e) {
            Log::warning('nikniq.com token request error', ['message' => $e->getMessage()]);

            return $fail('Could not reach nikniq.com. Please try again.');
        }

        $player = $response->successful() ? (array) $response->json('player') : [];
        $id = (string) ($player['id'] ?? '');
        $email = (string) ($player['email'] ?? '');
        if ($id === '' || $email === '') {
            Log::warning('nikniq.com token exchange failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
                'redirect_uri' => Nikniq::redirectUri(),
            ]);

            return $fail('nikniq.com sign-in failed. Please try again.');
        }

        $user = SocialLogin::resolve('nikniq_id', $id, $email, $player['name'] ?? null, $player['avatar'] ?? null);

        $expires = $response->json('expires_at');
        $user->forceFill([
            'nikniq_token' => $response->json('access_token'),
            'nikniq_token_expires_at' => $expires ? Carbon::parse($expires) : now()->addDays(30),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('game'));
    }
}
