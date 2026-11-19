<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f3d2e">
    <title>@yield('title', 'Play') · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Free mahjong solitaire in your browser. Match pairs of tiles across three layouts, take on the daily deal and climb the leaderboard. No download, no sign-up needed.')">
    <meta property="og:title" content="@yield('title', 'Play') · {{ config('app.name') }}">
    <meta property="og:description" content="Free mahjong solitaire in your browser, with a new shared deal every day.">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="@yield('body-class')">
    @php
        $menu = [
            ['Play', route('game'), request()->routeIs('game') && ! request()->has('daily')],
            ['Daily deal', route('game', ['daily' => 1]), request()->routeIs('game') && request()->has('daily')],
            ['Leaderboard', route('leaderboard'), request()->routeIs('leaderboard')],
            ['How to play', route('how-to-play'), request()->routeIs('how-to-play')],
        ];
    @endphp
    <header class="nav" id="nav">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-tile" aria-hidden="true">中</span>
            <span class="brand-name">{{ config('app.name') }}</span>
        </a>

        <button type="button" class="nav-toggle" aria-controls="nav-menu" aria-expanded="false" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-menu" id="nav-menu" aria-label="Main">
            <ul class="nav-links">
                @foreach ($menu as [$label, $url, $active])
                    <li><a href="{{ $url }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="nav-account">
                @auth
                    <details class="account">
                        <summary>
                            @if (auth()->user()->avatar)
                                <img class="avatar" src="{{ auth()->user()->avatar }}" alt="" referrerpolicy="no-referrer">
                            @else
                                <span class="avatar avatar-letter" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            @endif
                            <span class="nav-user">{{ auth()->user()->name }}</span>
                        </summary>
                        <div class="account-menu">
                            <p class="account-email">{{ auth()->user()->email }}</p>
                            <a href="{{ route('leaderboard') }}#mine">My scores</a>
                            @if (auth()->user()->nikniq_id)
                                <a href="{{ \App\Support\Nikniq::portal() }}/dashboard">nikniq.com dashboard</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">Log out</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}" @class(['nav-login', 'active' => request()->routeIs('login')])>Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-gold btn-sm">Sign up free</a>
                @endauth
            </div>
        </nav>
    </header>

    @yield('content')

    @hasSection('slim-footer')
        <footer class="site-footer site-footer-slim">
            <a href="https://nikniq.com">&larr; More games at nikniq.com</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('how-to-play') }}">How to play</a>
        </footer>
    @else
        <footer class="site-footer">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a class="brand" href="{{ route('home') }}">
                        <span class="brand-tile" aria-hidden="true">中</span>
                        <span>{{ config('app.name') }}</span>
                    </a>
                    <p>Free mahjong solitaire in your browser. No downloads, no ads in your way, and no account needed to play.</p>
                </div>
                <div>
                    <h3>Play</h3>
                    <ul>
                        <li><a href="{{ route('game') }}">New game</a></li>
                        <li><a href="{{ route('game', ['daily' => 1]) }}">Daily deal</a></li>
                        <li><a href="{{ route('leaderboard') }}">Leaderboard</a></li>
                        <li><a href="{{ route('how-to-play') }}">How to play</a></li>
                    </ul>
                </div>
                <div>
                    <h3>Account</h3>
                    <ul>
                        @auth
                            <li><a href="{{ route('leaderboard') }}#mine">My scores</a></li>
                        @else
                            <li><a href="{{ route('register') }}">Sign up</a></li>
                            <li><a href="{{ route('login') }}">Log in</a></li>
                        @endauth
                    </ul>
                </div>
                <div>
                    <h3>NikniQ</h3>
                    <ul>
                        <li><a href="https://nikniq.com">More free games</a></li>
                        <li><a href="https://nikniq.com/privacy">Privacy</a></li>
                        <li><a href="https://nikniq.com/terms">Terms</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} NikniQ</span>
                <a href="https://nikniq.com">&larr; Back to nikniq.com</a>
            </div>
        </footer>
    @endif

    <script>
        (function () {
            var nav = document.getElementById('nav'), btn = nav.querySelector('.nav-toggle');
            btn.addEventListener('click', function () {
                var open = nav.classList.toggle('open');
                btn.setAttribute('aria-expanded', open);
            });
            document.addEventListener('click', function (e) {
                document.querySelectorAll('details.account[open]').forEach(function (d) { if (!d.contains(e.target)) d.open = false; });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
