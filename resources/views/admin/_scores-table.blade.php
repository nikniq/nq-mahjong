@php $names = collect(config('mahjong.layouts'))->map(fn ($l) => $l['name']); @endphp
<div class="table-scroll">
<table class="table">
    <thead><tr><th>Player</th><th>Board</th><th class="num">Score</th><th class="num">Time</th><th class="num">H / S / U</th><th class="num">When</th><th></th></tr></thead>
    <tbody>
        @forelse ($scores as $s)
            <tr>
                <td>{{ $s->user?->name ?? '—' }}</td>
                <td>{{ $s->daily ? '☀ '.$s->daily->format('Y-m-d') : ($names[$s->layout] ?? $s->layout) }}</td>
                <td class="num">{{ number_format($s->score) }}</td>
                <td class="num">{{ sprintf('%d:%02d', intdiv($s->duration, 60), $s->duration % 60) }}</td>
                <td class="num">{{ $s->hints }} / {{ $s->shuffles }} / {{ $s->undos }}</td>
                <td class="num"><small>{{ $s->created_at->diffForHumans() }}</small></td>
                <td class="num">
                    <form method="POST" action="{{ route('admin.scores.destroy', $s) }}" onsubmit="return confirm('Remove this score from the leaderboard?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No scores yet.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
