@extends('layouts.app')

@section('title', 'Leaderboard')

@php
    $clock = fn (int $s) => sprintf('%d:%02d', intdiv($s, 60), $s % 60);
@endphp

@section('content')
<main class="page">
    <h1>Leaderboard</h1>

    <nav class="tabs" aria-label="Board">
        <a href="{{ route('leaderboard', ['layout' => 'daily']) }}" @class(['tab', 'active' => $daily])>
            ☀ Today's deal <small>{{ \App\Support\Mahjong::today() }}</small>
        </a>
        @foreach ($layouts as $key => $item)
            <a href="{{ route('leaderboard', ['layout' => $key]) }}" @class(['tab', 'active' => ! $daily && $key === $layout])>
                {{ $item['name'] }} <small>{{ $item['level'] }}</small>
            </a>
        @endforeach
    </nav>

    <section class="card">
        @if (count($rows))
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Player</th><th class="num">Best score</th><th class="num">Fastest</th><th class="num">Wins</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        <tr @class(['me' => auth()->id() === $row['id']])>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ number_format($row['best']) }}</td>
                            <td class="num">{{ $clock((int) $row['fastest']) }}</td>
                            <td class="num">{{ $row['wins'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">No scores here yet. <a href="{{ $daily ? route('game', ['daily' => 1]) : route('game', ['layout' => $layout]) }}">Be the first.</a></p>
        @endif
    </section>

    <h2 id="mine">Your recent games</h2>
    <section class="card">
        @guest
            <p class="empty"><a href="{{ route('login') }}">Log in</a> or <a href="{{ route('register') }}">register</a> to record your scores.</p>
        @else
            @if ($mine->count())
                <table class="table">
                    <thead>
                        <tr><th>When</th><th class="num">Score</th><th class="num">Time</th><th class="num">Hints</th><th class="num">Shuffles</th><th class="num">Undos</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($mine as $score)
                            <tr>
                                <td>{{ $score->created_at->diffForHumans() }}</td>
                                <td class="num">{{ number_format($score->score) }}</td>
                                <td class="num">{{ $clock($score->duration) }}</td>
                                <td class="num">{{ $score->hints }}</td>
                                <td class="num">{{ $score->shuffles }}</td>
                                <td class="num">{{ $score->undos }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty">You haven't cleared this board yet. <a href="{{ $daily ? route('game', ['daily' => 1]) : route('game', ['layout' => $layout]) }}">Play now.</a></p>
            @endif
        @endguest
    </section>
</main>
@endsection
