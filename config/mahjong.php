<?php

/*
| Game rules shared between the browser (public_html/js/game.js) and the
| server-side score validation. The game itself runs entirely client-side;
| the server only needs enough to sanity-check a submitted score.
|
| `tiles` must match the tile count the layout produces in game.js.
*/

return [

    // Comma-separated emails that always get the admin area (see also `php artisan mahjong:admin`).
    'admin_emails' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    'layouts' => [
        'garden' => ['name' => 'Garden', 'level' => 'Easy', 'tiles' => 48],
        'bridge' => ['name' => 'Twin Towers', 'level' => 'Medium', 'tiles' => 88],
        'pyramid' => ['name' => 'Pyramid', 'level' => 'Hard', 'tiles' => 82],
        'turtle' => ['name' => 'Turtle', 'level' => 'Classic', 'tiles' => 144],
    ],

    // The shared "deal of the day" uses this layout with a date-derived seed.
    'daily_layout' => 'turtle',

    'scoring' => [
        'pair' => 100,            // base points per matched pair
        'combo_window' => 6,      // seconds between matches to keep a streak
        'combo_step' => 0.25,     // multiplier added per streak step
        'combo_max' => 3,         // multiplier cap
        'par_per_pair' => 10,     // seconds of par time per pair
        'time_bonus' => 5,        // points per second under par
        'hint' => 50,             // penalties
        'shuffle' => 200,
        'undo' => 25,
    ],

];
