<?php

namespace App\Support;

use App\Models\Score;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** The nikniq.com games portal: SSO settings and activity reporting. */
class Nikniq
{
    public static function enabled(): bool
    {
        return (bool) config('services.nikniq.client_id');
    }

    public static function portal(): string
    {
        return rtrim((string) config('services.nikniq.url', 'https://nikniq.com'), '/');
    }

    /** Must exactly match a redirect URI registered for the game on the portal. */
    public static function redirectUri(): string
    {
        return (string) (config('services.nikniq.redirect') ?: route('auth.callback'));
    }

    /**
     * Report a finished game to the portal so it shows on the player's nikniq.com
     * dashboard. Runs after the response is sent; failures are only logged.
     */
    public static function reportWin(Score $score): void
    {
        $user = $score->user;
        if (! self::enabled() || ! $user->nikniq_token || $user->nikniq_token_expires_at?->isPast()) {
            return;
        }

        dispatch(function () use ($score, $user) {
            try {
                $response = Http::withToken($user->nikniq_token)->acceptJson()->timeout(5)
                    ->post(self::portal().'/api/games/'.config('services.nikniq.game').'/events', [
                        'event' => 'game_over',
                        'won' => true,
                        'score' => $score->score,
                        'duration_seconds' => $score->duration,
                        'level' => $score->daily ? 'daily-'.$score->daily : $score->layout,
                        'metadata' => ['layout' => $score->layout, 'hints' => $score->hints, 'shuffles' => $score->shuffles, 'undos' => $score->undos],
                    ]);
                if ($response->status() === 401) {
                    $user->forceFill(['nikniq_token' => null, 'nikniq_token_expires_at' => null])->save();
                }
            } catch (\Throwable $e) {
                Log::info('nikniq.com report failed', ['message' => $e->getMessage()]);
            }
        })->afterResponse();
    }
}
