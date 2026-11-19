@extends('admin._layout')

@section('title', 'Scores · Admin')
@section('admin-title', 'Scores')

@section('admin')
<form class="admin-filter" method="GET">
    <label class="select"><span class="sr-only">Board</span>
        <select name="layout" onchange="this.form.submit()">
            <option value="">All boards</option>
            <option value="daily" @selected($layout === 'daily')>Daily deals</option>
            @foreach (config('mahjong.layouts') as $key => $l)
                <option value="{{ $key }}" @selected($layout === $key)>{{ $l['name'] }}</option>
            @endforeach
        </select>
    </label>
    <label class="select"><span class="sr-only">Sort</span>
        <select name="sort" onchange="this.form.submit()">
            <option value="">Newest first</option>
            <option value="score" @selected(request('sort') === 'score')>Highest score</option>
        </select>
    </label>
</form>
<section class="card">
    @include('admin._scores-table')
    {{ $scores->links('admin._pager') }}
</section>
@endsection
