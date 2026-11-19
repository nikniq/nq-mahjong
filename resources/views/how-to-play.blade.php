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

    <h2>The daily deal</h2>
    <p>Each day (midnight UTC) everyone gets the same Turtle board. Clear it signed in to appear on that day's leaderboard.</p>

    <h2>Keyboard shortcuts</h2>
    <table class="table keys">
        <tbody>
            <tr><td><kbd>H</kbd></td><td>Hint</td></tr>
            <tr><td><kbd>U</kbd></td><td>Undo</td></tr>
            <tr><td><kbd>P</kbd> or <kbd>Space</kbd></td><td>Pause / resume</td></tr>
            <tr><td><kbd>Esc</kbd></td><td>Deselect the current tile</td></tr>
        </tbody>
    </table>

    <p class="center"><a class="btn btn-gold btn-lg" href="{{ route('game') }}">Play now</a></p>
</main>
@endsection
