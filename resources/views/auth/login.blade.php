@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<main class="page page-narrow">
    <form class="card form" method="POST" action="{{ route('login') }}">
        @csrf
        <h1>Log in</h1>
        <p class="muted">Sign in so your cleared boards count on the leaderboard.</p>

        @include('auth._social', ['verb' => 'Log in'])

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>

        <button type="submit" class="btn btn-gold btn-block">Log in</button>
        <p class="muted">New here? <a href="{{ route('register') }}">Create an account</a></p>
    </form>
</main>
@endsection
