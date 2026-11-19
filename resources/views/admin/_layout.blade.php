@extends('layouts.app')

@section('body-class', 'page-admin')

@section('content')
<main class="page page-wide admin">
    <div class="admin-head">
        <h1>@yield('admin-title')</h1>
        <nav class="tabs" aria-label="Admin">
            <a href="{{ route('admin.dashboard') }}" @class(['tab', 'active' => request()->routeIs('admin.dashboard')])>Overview</a>
            <a href="{{ route('admin.users') }}" @class(['tab', 'active' => request()->routeIs('admin.users')])>Players</a>
            <a href="{{ route('admin.scores') }}" @class(['tab', 'active' => request()->routeIs('admin.scores')])>Scores</a>
        </nav>
    </div>
    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    @yield('admin')
</main>
@endsection
