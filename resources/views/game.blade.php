@extends('layouts.app')

@section('title', 'Play')
@section('body-class', 'page-game')
@section('slim-footer', true)

@section('content')
<main class="game">
    <section class="hud" aria-label="Game status">
        <div class="stats">
            <div class="stat"><span class="stat-label">Score</span><span class="stat-value" id="stat-score">0</span></div>
            <div class="stat"><span class="stat-label">Time</span><span class="stat-value" id="stat-time">0:00</span></div>
            <div class="stat"><span class="stat-label">Tiles</span><span class="stat-value" id="stat-tiles">0</span></div>
            <div class="stat"><span class="stat-label">Moves</span><span class="stat-value" id="stat-moves">0</span></div>
            <div class="stat stat-combo" id="stat-combo" hidden><span class="stat-label">Combo</span><span class="stat-value" id="stat-combo-value">×1</span></div>
        </div>
        <div class="controls">
            <span class="daily-badge" id="daily-badge" hidden>☀ Daily deal</span>
            <label class="select" id="layout-wrap">
                <span class="sr-only">Layout</span>
                <select id="layout-select">
                    @foreach (config('mahjong.layouts') as $key => $layout)
                        <option value="{{ $key }}">{{ $layout['name'] }} · {{ $layout['level'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="button" class="btn" id="btn-hint" title="Show a matching pair (H, −{{ config('mahjong.scoring.hint') }})">Hint</button>
            <button type="button" class="btn" id="btn-undo" title="Take back the last pair (U, −{{ config('mahjong.scoring.undo') }})">Undo</button>
            <button type="button" class="btn" id="btn-shuffle" title="Reshuffle the remaining tiles (−{{ config('mahjong.scoring.shuffle') }})">Shuffle</button>
            <button type="button" class="btn btn-icon" id="btn-help" title="How to play (?)" aria-label="How to play">?</button>
            <button type="button" class="btn btn-icon" id="btn-pause" title="Pause (P)" aria-label="Pause">❚❚</button>
            <details class="menu" id="game-menu">
                <summary class="btn btn-icon" title="More" aria-label="More options">⋯</summary>
                <div class="menu-panel">
                    <button type="button" id="btn-restart">Restart this deal</button>
                    <button type="button" id="btn-daily">Play today's deal</button>
                    <button type="button" id="btn-ach">Achievements</button>
                    <hr>
                    <label class="toggle"><input type="checkbox" id="opt-sound"> Sound effects</label>
                    <label class="toggle"><input type="checkbox" id="opt-dim"> Dim blocked tiles</label>
                </div>
            </details>
            <button type="button" class="btn btn-gold" id="btn-new">New game</button>
        </div>
    </section>

    <div class="progress" aria-hidden="true"><span id="progress-bar"></span></div>

    <p class="notice" id="notice" role="status" aria-live="polite" hidden></p>

    <section class="board-wrap" id="board-wrap">
        <div class="board" id="board" aria-label="Mahjong board"></div>
        <div class="pause-overlay" id="pause-overlay" hidden>
            <div>
                <p class="pause-title">Paused</p>
                <button type="button" class="btn btn-gold btn-lg" id="btn-resume">Resume</button>
            </div>
        </div>
    </section>

    <footer class="help">
        <span class="tip" id="tip">💡 Match pairs of identical free tiles: nothing on top, and the left or right side open.</span>
        <span id="best-line"></span>
    </footer>
</main>

<dialog class="modal" id="win-dialog" aria-labelledby="win-title">
    <div class="confetti" id="confetti" aria-hidden="true"></div>
    <h2 id="win-title">Board cleared!</h2>
    <p class="final-score" id="win-score">0</p>
    <dl class="breakdown" id="win-breakdown"></dl>
    <p class="streak-line" id="win-streak" hidden></p>
    <ul class="ach-new" id="win-ach" hidden></ul>
    <p class="save-status" id="win-save" role="status"></p>
    <div id="win-guest" hidden>
        <ul class="benefits">
            <li>Put this score on the leaderboard</li>
            <li>Track your best on every board and the daily deal</li>
            <li>Free, one click with Google or nikniq.com</li>
        </ul>
        <div class="modal-actions">
            <a class="btn btn-gold" href="{{ route('register') }}">Save my score — free</a>
            <a class="btn" href="{{ route('login') }}">Log in</a>
        </div>
    </div>
    <div class="modal-actions">
        <button type="button" class="btn btn-gold" id="win-new">Play again</button>
        <button type="button" class="btn" id="win-share">Copy result</button>
        <a class="btn" href="{{ route('leaderboard') }}" id="win-board">Leaderboard</a>
    </div>
</dialog>

<dialog class="modal modal-help" id="help-dialog" aria-labelledby="help-title">
    <h2 id="help-title">How to play</h2>
    <p class="muted">Clear the board by removing every tile in matching pairs.</p>
    <ol class="help-steps">
        <li>
            <strong>Pick a free tile</strong>
            A tile is free when nothing sits on top of it and its left <em>or</em> right side is open.
            Blocked tiles shake when clicked.
        </li>
        <li>
            <strong>Click its twin</strong>
            Two identical free tiles disappear together.
            <span class="help-tiles"><span class="mini">東</span>+<span class="mini">東</span> · any flower <span class="mini ink-r">梅</span>+<span class="mini ink-r">菊</span> · any season <span class="mini ink-b">春</span>+<span class="mini ink-b">冬</span></span>
        </li>
        <li>
            <strong>Match fast for combos</strong>
            Pairs within {{ config('mahjong.scoring.combo_window') }}s build a combo up to ×{{ config('mahjong.scoring.combo_max') }}. Beat par for a time bonus.
        </li>
        <li>
            <strong>Stuck? Get help</strong>
            <b>Hint</b> shows a pair, <b>Undo</b> takes one back, <b>Shuffle</b> re-deals the rest. Each costs a few points.
        </li>
    </ol>
    <p class="help-tip">💡 Start with tiles on top of tall stacks and at the ends of long rows. They free up the most tiles.</p>
    <p class="help-keys">Keys: <kbd>H</kbd> hint · <kbd>U</kbd> undo · <kbd>P</kbd> pause · <kbd>?</kbd> this guide</p>
    <div class="modal-actions">
        <button type="button" class="btn btn-gold" id="help-close">Let's play</button>
        <a class="btn" href="{{ route('how-to-play') }}">Full rules &amp; strategy</a>
    </div>
</dialog>

<dialog class="modal" id="ach-dialog" aria-labelledby="ach-title">
    <h2 id="ach-title">Achievements</h2>
    <ul class="ach-list" id="ach-list"></ul>
    <div class="modal-actions"><button type="button" class="btn btn-gold" id="ach-close">Close</button></div>
</dialog>

<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
@php
    $clientConfig = \App\Support\Mahjong::clientConfig() + [
        'user' => auth()->user()?->only('name'),
        'urls' => [
            'scores' => route('scores.store'),
            'leaderboard' => route('leaderboard'),
            'play' => route('game'),
        ],
    ];
@endphp
<script>
    window.NQ = @json($clientConfig);
</script>
<script src="{{ asset('js/game.js') }}?v={{ filemtime(public_path('js/game.js')) }}" defer></script>
@endpush
