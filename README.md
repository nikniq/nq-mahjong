# NQ Mahjong

Mahjong solitaire on Laravel 13. The game runs entirely in the browser; the
server only serves the page, handles accounts, and records finished games.

- Marketing landing page at `/`, the game at `/play`, rules at `/how-to-play`
- Three layouts: Garden (48 tiles), Twin Towers (88), Turtle (144)
- A shared **daily deal** (same Turtle board for everyone, per UTC day) with its own leaderboard
- Every deal is generated to be solvable; hint, undo, shuffle, restart, pause,
  sound effects and a "dim blocked tiles" option
- Guests can play freely. A game in progress and personal bests are kept in `localStorage`
- Signed-in players have each cleared board saved to the leaderboard. A guest's
  last win is held on the device and uploaded as soon as they log in or register

## Sign-in options

Email/password always works. Two optional providers appear on the login and
sign-up pages once configured in `.env` (see `.env.example` for details):

- **Google** — set `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`, with
  `https://your-domain/auth/google/callback` as the authorised redirect URI.
- **nikniq.com** — set `NIKNIQ_CLIENT_ID` to Mahjong's client id from the
  portal's `/admin/games`, and make sure the portal lists this site's
  `/auth/callback` URL as an allowed redirect URI. It uses PKCE, so no secret is
  needed. Guests already logged in on nikniq.com are signed in silently
  (`NIKNIQ_AUTO_SIGN_ON`), and wins by nikniq players are reported to the portal
  so they appear on their nikniq.com dashboard.

Accounts are linked by email, so the same person signing in different ways
lands on one player.

## Run locally

```sh
composer setup        # install, .env, app key, migrate (SQLite by default)
php artisan serve
php artisan test
```

There is no Node/Vite build step: the front end is plain files in
`public_html/css/app.css` and `public_html/js/game.js`.

## Deploying

The web root is **`public_html/`**, not `public/` (set in `bootstrap/app.php`
via `usePublicPath`). Point the virtual host at that directory, or on shared
hosting upload the project so that `public_html` is the account's web root and
everything else sits beside it.

## Where things live

| Path | Purpose |
| --- | --- |
| `public_html/js/game.js` | Layouts, deal generator, rules, rendering, scoring |
| `config/mahjong.php` | Layout tile counts and scoring rules, shared with the browser |
| `app/Http/Controllers/ScoreController.php` | Score submission and leaderboard |
| `app/Http/Controllers/AuthController.php` | Register, log in, log out |

Adding a layout means adding it to `LAYOUTS` in `game.js` and, with the same
key and tile count, to `config/mahjong.php`.

## Score integrity

Because play happens client-side, a submitted score cannot be proven genuine.
The server rejects results that are impossible under the rules (above the
maximum reachable score, or faster than the tiles can be clicked) and
rate-limits submissions, which is enough for a casual leaderboard but not for
prizes.

## Admin area

`/admin` shows players, sign-ups, games per day and sign-in providers, and lets admins
search players, grant/revoke admin, delete players, and remove suspicious scores.
Grant access with `php artisan mahjong:admin you@example.com`, or list emails in `ADMIN_EMAILS`.
