@extends('layouts.app')

@section('title', 'How to play')
@section('description', 'Rules of mahjong solitaire: which tiles are free, which tiles match, and how scoring, combos and the daily deal work.')

@php($s = config('mahjong.scoring'))

@section('content')
<main class="page prose">
    <h1>How to play</h1>
    <p class="lead">Mahjong solitaire is a matching game for one. The goal is to remove every tile from the board in pairs.</p>

    <h2>Free tiles</h2>
    <p>You can only take a tile that is <strong>free</strong>:</p>
    <ul>
        <li>no tile is lying on top of it, even partly, and</li>
        <li>its left side <em>or</em> its right side is open.</li>
    </ul>
    <p>Blocked tiles give a little shake when you click them. Turn on <em>Dim blocked tiles</em> in the game's settings to see at a glance which tiles are free.</p>

    <h2>Matching tiles</h2>
    <ul>
        <li><strong>Suits</strong> — Dots, Bamboo and Characters, numbered 1 to 9: match the same suit and number.</li>
        <li><strong>Winds</strong> (東 南 西 北) and <strong>Dragons</strong> (中 發 and the blue frame): match identical tiles.</li>
        <li><strong>Flowers</strong> (梅 蘭 菊 竹) match any other flower, and <strong>Seasons</strong> (春 夏 秋 冬) match any other season.</li>
    </ul>

    <h2>Scoring</h2>
    <ul>
        <li>Each pair is worth <strong>{{ $s['pair'] }}</strong> points.</li>
        <li>Match again within {{ $s['combo_window'] }} seconds to build a <strong>combo</strong>: each step adds ×{{ $s['combo_step'] }}, up to ×{{ $s['combo_max'] }}.</li>
        <li>Clear the board under par for <strong>{{ $s['time_bonus'] }} points per second</strong> to spare.</li>
        <li>A hint costs {{ $s['hint'] }}, an undo {{ $s['undo'] }} and a shuffle {{ $s['shuffle'] }} points.</li>
    </ul>

    <h2>Strategy tips</h2>
    <ol>
        <li><strong>Unstack the tall piles first.</strong> Tiles under a high stack are the hardest to reach. Freeing them early keeps your options open.</li>
        <li><strong>Open long rows from the ends.</strong> A long row only frees one tile at a time from each end. Start on rows early.</li>
        <li><strong>Watch for the third and fourth tile.</strong> If three matching tiles are free, pick the pair that frees the most new tiles. If two are free and the other two are buried, consider waiting.</li>
        <li><strong>Don't grab the first pair you see.</strong> Look at what each match uncovers. Use the <em>Moves</em> counter to see how many pairs are open.</li>
        <li><strong>Use undo before shuffle.</strong> An undo costs {{ $s['undo'] }} points; a shuffle costs {{ $s['shuffle'] }} and clears your undo history.</li>
        <li><strong>Chase combos on easy stretches.</strong> When several pairs are open at once, take them quickly for up to ×{{ $s['combo_max'] }} points.</li>
    </ol>

    <h2>The layouts</h2>
    <ul>
        @foreach (config('mahjong.layouts') as $layout)
            <li><strong>{{ $layout['name'] }}</strong> ({{ $layout['level'] }}, {{ $layout['tiles'] }} tiles)</li>
        @endforeach
    </ul>

    <h2>Achievements</h2>
    <p>Unlock badges for things like winning without help, beating par, reaching the top combo, clearing every layout and keeping a daily streak. Open <em>⋯ → Achievements</em> in the game to see them all. They are saved in your browser.</p>

    <h2>The daily deal</h2>
    <p>Each day (midnight UTC) everyone gets the same Turtle board. Clear it signed in to appear on that day's leaderboard.</p>

    <h2>Keyboard shortcuts</h2>
    <table class="table keys">
        <tbody>
            <tr><td><kbd>H</kbd></td><td>Hint</td></tr>
            <tr><td><kbd>U</kbd></td><td>Undo</td></tr>
            <tr><td><kbd>P</kbd> or <kbd>Space</kbd></td><td>Pause / resume</td></tr>
            <tr><td><kbd>Esc</kbd></td><td>Deselect the current tile</td></tr>
            <tr><td><kbd>?</kbd></td><td>Show the quick guide</td></tr>
        </tbody>
    </table>

    <p class="center"><a class="btn btn-gold btn-lg" href="{{ route('game') }}">Play now</a></p>
</main>
@endsection
