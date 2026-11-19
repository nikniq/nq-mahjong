@extends('admin._layout')

@section('title', 'Admin')
@section('admin-title', 'Admin overview')

@php
    $peak = max(1, $chart->max(fn ($d) => max($d['signups'], $d['games'])));
    $names = collect(config('mahjong.layouts'))->map(fn ($l) => $l['name']);
@endphp

@section('admin')
<section class="kpis">
    @foreach ($totals as $label => $value)
        <div class="kpi"><span class="kpi-label">{{ $label }}</span><span class="kpi-value">{{ number_format($value) }}</span></div>
    @endforeach
</section>

<section class="card">
    <h2 class="card-title">Last 14 days</h2>
    <div class="bars" role="img" aria-label="Sign-ups and saved games per day for the last 14 days">
        @foreach ($chart as $d)
            <div class="bar-day" title="{{ $d['day'] }}: {{ $d['signups'] }} sign-ups, {{ $d['games'] }} games">
                <div class="bar-stack">
                    <span class="bar bar-games" style="height: {{ $d['games'] / $peak * 100 }}%"></span>
                    <span class="bar bar-signups" style="height: {{ $d['signups'] / $peak * 100 }}%"></span>
                </div>
                <span class="bar-label">{{ \Illuminate\Support\Carbon::parse($d['day'])->format('j') }}</span>
            </div>
        @endforeach
    </div>
    <p class="legend"><span class="dot dot-games"></span> Games saved <span class="dot dot-signups"></span> Sign-ups</p>
</section>

<div class="section-split">
    <section class="card">
        <h2 class="card-title">How players sign in</h2>
        <dl class="breakdown">
            @foreach ($providers as $label => $n)<dt>{{ $label }}</dt><dd>{{ number_format($n) }}</dd>@endforeach
        </dl>
        <h2 class="card-title">Games by layout</h2>
        <dl class="breakdown">
            @forelse ($layouts as $key => $n)<dt>{{ $names[$key] ?? $key }}</dt><dd>{{ number_format($n) }}</dd>@empty<dt>No games yet</dt><dd>0</dd>@endforelse
        </dl>
    </section>
    <section class="card">
        <h2 class="card-title">Newest players</h2>
        <table class="table">
            <tbody>
                @forelse ($recentUsers as $u)
                    <tr><td>{{ $u->name }}<br><small class="muted">{{ $u->email }}</small></td><td class="num"><small>{{ $u->created_at->diffForHumans() }}</small></td></tr>
                @empty
                    <tr><td class="empty">No players yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <a class="card-link" href="{{ route('admin.users') }}">All players &rarr;</a>
    </section>
</div>

<section class="card">
    <h2 class="card-title">Latest scores</h2>
    @include('admin._scores-table', ['scores' => $recentScores])
    <a class="card-link" href="{{ route('admin.scores') }}">All scores &rarr;</a>
</section>
@endsection
