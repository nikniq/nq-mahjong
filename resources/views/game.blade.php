@extends('layouts.app')

@section('title', 'Play')
@section('body-class', 'page-game')

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
            <label class="select">
                <span class="sr-only">Layout</span>
                <select id="layout-select">
                    @foreach (config('mahjong.layouts') as $key => $layout)
                        <option value="{{ $key }}">{{ $layout['name'] }} · {{ $layout['level'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="button" class="btn" id="btn-hint" title="Show a matching pair (−{{ config('mahjong.scoring.hint') }})">Hint</button>
            <button type="button" class="btn" id="btn-undo" title="Take back the last pair (−{{ config('mahjong.scoring.undo') }})">Undo</button>
            <button type="button" class="btn" id="btn-shuffle" title="Reshuffle the remaining tiles (−{{ config('mahjong.scoring.shuffle') }})">Shuffle</button>
            <button type="button" class="btn" id="btn-restart" title="Replay this deal from the start">Restart</button>
            <button type="button" class="btn btn-gold" id="btn-new">New game</button>
        </div>
    </section>

    <p class="notice" id="notice" role="status" aria-live="polite" hidden></p>

    <section class="board-wrap" id="board-wrap">
        <div class="board" id="board" aria-label="Mahjong board"></div>
    </section>

    <footer class="help">
        Match pairs of identical free tiles. A tile is free when nothing sits on it and its left or right side is open.
        Any flower matches any flower, any season any season. Match quickly to build a combo.
        <span id="best-line"></span>
    </footer>
</main>

<dialog class="modal" id="win-dialog" aria-labelledby="win-title">
    <h2 id="win-title">Board cleared!</h2>
    <p class="final-score" id="win-score">0</p>
    <dl class="breakdown" id="win-breakdown"></dl>
    <p class="save-status" id="win-save" role="status"></p>
    <div class="modal-actions" id="win-guest" hidden>
        <a class="btn btn-gold" href="{{ route('register') }}">Register to save it</a>
        <a class="btn" href="{{ route('login') }}">Log in</a>
    </div>
    <div class="modal-actions">
        <button type="button" class="btn btn-gold" id="win-new">Play again</button>
        <a class="btn" href="{{ route('leaderboard') }}" id="win-board">Leaderboard</a>
    </div>
</dialog>

<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
@php
    $clientConfig = \App\Support\Mahjong::clientConfig() + [
        'user' => auth()->user()?->only('name'),
        'urls' => ['scores' => route('scores.store'), 'leaderboard' => route('leaderboard')],
    ];
@endphp
<script>
    window.NQ = @json($clientConfig);
</script>
<script src="{{ asset('js/game.js') }}?v={{ filemtime(public_path('js/game.js')) }}" defer></script>
@endpush
