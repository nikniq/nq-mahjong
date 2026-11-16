<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f3d2e">
    <title>@yield('title', 'Play') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="@yield('body-class')">
    <header class="nav">
        <a class="brand" href="{{ route('game') }}">
            <span class="brand-tile" aria-hidden="true">中</span>
            <span>{{ config('app.name') }}</span>
        </a>
        <nav class="nav-links">
            <a href="{{ route('game') }}" @class(['active' => request()->routeIs('game')])>Play</a>
            <a href="{{ route('leaderboard') }}" @class(['active' => request()->routeIs('leaderboard')])>Leaderboard</a>
            @auth
                <span class="nav-user" title="{{ auth()->user()->email }}">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link-btn">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" @class(['active' => request()->routeIs('login')])>Log in</a>
                <a href="{{ route('register') }}" class="btn btn-gold btn-sm">Register</a>
            @endauth
        </nav>
    </header>

    @yield('content')

    <footer class="site-footer">
        <a href="https://nikniq.com">&larr; Back to nikniq.com</a>
    </footer>

    @stack('scripts')
</body>
</html>
