@extends('layouts.app')

@section('title', 'Register')

@section('content')
<main class="page page-narrow">
    <form class="card form" method="POST" action="{{ route('register') }}">
        @csrf
        <h1>Create an account</h1>
        <p class="muted">Playing is free without one. An account just records your scores.</p>

        <label for="name">Display name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus maxlength="30" autocomplete="nickname">
        @error('name') <p class="error">{{ $message }}</p> @enderror

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username">
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">

        <button type="submit" class="btn btn-gold btn-block">Register</button>
        <p class="muted">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </form>
</main>
@endsection
