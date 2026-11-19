<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Finds or creates the local player for an external identity (Google, nikniq.com).
 * Links by provider id first, then by email (both providers verify emails), and
 * otherwise creates a player with a unique display name.
 */
class SocialLogin
{
    /** @param 'google_id'|'nikniq_id' $column */
    public static function resolve(string $column, string $id, string $email, ?string $name, ?string $avatar): User
    {
        $email = Str::lower($email);
        $user = User::where($column, $id)->first() ?? User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => self::uniqueName($name ?: Str::before($email, '@')),
                'email' => $email,
                'password' => Str::random(40),
            ]);
        }

        $user->forceFill([
            $column => $id,
            'avatar' => $user->avatar ?: $avatar,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    /** Names appear on the leaderboard: keep them short, clean and unique. */
    public static function uniqueName(string $raw): string
    {
        $base = trim((string) preg_replace('/[^\pL\pN _.-]+/u', '', $raw)) ?: 'Player';
        $base = Str::limit($base, 24, '');
        $name = $base;
        for ($i = 2; User::where('name', $name)->exists(); $i++) {
            $name = $base.' '.$i;
        }

        return $name;
    }
}
