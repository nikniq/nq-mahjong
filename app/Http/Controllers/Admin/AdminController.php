<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ScoreController;
use App\Models\Score;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $since = Carbon::today()->subDays(13);
        $days = collect(range(0, 13))->map(fn ($i) => $since->copy()->addDays($i)->toDateString());

        $signups = User::where('created_at', '>=', $since)->get(['created_at'])
            ->countBy(fn ($u) => $u->created_at->toDateString());
        $games = Score::where('created_at', '>=', $since)->get(['created_at'])
            ->countBy(fn ($s) => $s->created_at->toDateString());

        $activePlayers = Score::where('created_at', '>=', Carbon::now()->subDays(7))->distinct()->count('user_id');

        return view('admin.dashboard', [
            'totals' => [
                'Players' => User::count(),
                'New this week' => User::where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                'Active (7 days)' => $activePlayers,
                'Games saved' => Score::count(),
                'Daily deals' => Score::whereNotNull('daily')->count(),
            ],
            'providers' => [
                'Google' => User::whereNotNull('google_id')->count(),
                'nikniq.com' => User::whereNotNull('nikniq_id')->count(),
                'Email only' => User::whereNull('google_id')->whereNull('nikniq_id')->count(),
            ],
            'layouts' => Score::selectRaw('layout, COUNT(*) as n')->groupBy('layout')->pluck('n', 'layout'),
            'chart' => $days->map(fn ($d) => ['day' => $d, 'signups' => $signups[$d] ?? 0, 'games' => $games[$d] ?? 0]),
            'recentUsers' => User::latest()->limit(8)->get(),
            'recentScores' => Score::with('user:id,name')->latest()->limit(8)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        $users = User::query()
            ->withCount('scores')->withMax('scores', 'score')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.users', compact('users', 'q'));
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('status', "You can't remove your own admin access.");
        }
        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->name.($user->is_admin ? ' is now an admin.' : ' is no longer an admin.'));
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('status', "You can't delete your own account here.");
        }
        $user->scores()->delete();
        $user->delete();
        self::flushBoards();

        return back()->with('status', 'Deleted '.$user->name.' and their scores.');
    }

    public function scores(Request $request): View
    {
        $layout = $request->query('layout');
        $scores = Score::with('user:id,name,email')
            ->when($layout === 'daily', fn ($q) => $q->whereNotNull('daily'))
            ->when($layout && $layout !== 'daily', fn ($q) => $q->where('layout', $layout)->whereNull('daily'))
            ->orderBy($request->query('sort') === 'score' ? 'score' : 'created_at', 'desc')
            ->paginate(30)->withQueryString();

        return view('admin.scores', compact('scores', 'layout'));
    }

    public function destroyScore(Score $score): RedirectResponse
    {
        $score->delete();
        self::flushBoards();

        return back()->with('status', 'Score removed from the leaderboard.');
    }

    private static function flushBoards(): void
    {
        Cache::forget('home:stats');
        foreach (array_keys(config('mahjong.layouts')) as $layout) {
            Cache::forget(ScoreController::cacheKey($layout, null));
        }
        foreach (\App\Support\Mahjong::acceptedDailyDates() as $d) {
            Cache::forget(ScoreController::cacheKey(\App\Support\Mahjong::dailyLayout($d), $d));
        }
    }
}
