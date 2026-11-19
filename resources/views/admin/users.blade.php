@extends('admin._layout')

@section('title', 'Players · Admin')
@section('admin-title', 'Players')

@section('admin')
<form class="admin-filter" method="GET">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search name or email" aria-label="Search players">
    <button class="btn" type="submit">Search</button>
</form>
<section class="card">
    <div class="table-scroll">
    <table class="table">
        <thead><tr><th>Player</th><th>Sign-in</th><th class="num">Games</th><th class="num">Best</th><th class="num">Joined</th><th></th></tr></thead>
        <tbody>
            @forelse ($users as $u)
                <tr>
                    <td>{{ $u->name }} @if ($u->isAdmin())<span class="pill">admin</span>@endif<br><small class="muted">{{ $u->email }}</small></td>
                    <td><small>{{ collect(['Google' => $u->google_id, 'nikniq.com' => $u->nikniq_id])->filter()->keys()->join(', ') ?: 'Email' }}</small></td>
                    <td class="num">{{ number_format($u->scores_count) }}</td>
                    <td class="num">{{ $u->scores_max_score ? number_format($u->scores_max_score) : '—' }}</td>
                    <td class="num"><small>{{ $u->created_at->format('Y-m-d') }}</small></td>
                    <td class="num actions">
                        @unless ($u->is(auth()->user()))
                            <form method="POST" action="{{ route('admin.users.admin', $u) }}">
                                @csrf
                                <button class="btn btn-sm" type="submit">{{ $u->is_admin ? 'Revoke admin' : 'Make admin' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Delete {{ addslashes($u->name) }} and all their scores? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No players found.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    {{ $users->links('admin._pager') }}
</section>
@endsection
