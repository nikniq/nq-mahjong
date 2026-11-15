# NQ Mahjong

Mahjong solitaire on Laravel 13. The game runs entirely in the browser; the
server only serves the page, handles accounts, and records finished games.

- Three layouts: Garden (48 tiles), Twin Towers (88), Turtle (144)
- Every deal is generated to be solvable; hint, undo, shuffle, restart
- Guests can play freely. A game in progress and personal bests are kept in `localStorage`
- Signed-in players have each cleared board saved to the leaderboard. A guest's
  last win is held on the device and uploaded as soon as they log in or register

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
