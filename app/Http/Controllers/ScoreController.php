<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Support\Mahjong;
use App\Support\Nikniq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScoreController extends Controller
{
    /** Record a finished game for the signed-in player. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'layout' => ['required', 'string', Rule::in(array_keys(config('mahjong.layouts')))],
            // The browser's idea of "today" can be a day either side of the server's.
            'daily' => ['nullable', 'date_format:Y-m-d', Rule::in(Mahjong::acceptedDailyDates())],
        ]);
        $layout = $request->string('layout')->value();
        $daily = $request->input('daily');
        if ($daily && $layout !== Mahjong::dailyLayout($daily)) {
            return response()->json(['message' => 'Wrong layout for the daily deal.', 'errors' => ['layout' => ['Wrong layout.']]], 422);
        }

        // The game runs in the browser, so all we can do is reject results
        // that are impossible under the scoring rules.
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:'.Mahjong::maxScore($layout)],
            'duration' => ['required', 'integer', 'min:'.Mahjong::minDuration($layout), 'max:86400'],
            'hints' => ['required', 'integer', 'min:0', 'max:9999'],
            'shuffles' => ['required', 'integer', 'min:0', 'max:9999'],
            'undos' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $user = $request->user();
        $scope = fn ($q) => $daily ? $q->whereDate('daily', $daily) : $q->where('layout', $layout)->whereNull('daily');
        $previousBest = (int) $user->scores()->where($scope)->max('score');

        $score = $user->scores()->create($data + ['layout' => $layout, 'daily' => $daily]);
        Cache::forget(self::cacheKey($layout, $daily));
        Cache::forget('home:stats');
        Nikniq::reportWin($score);

        $best = max($previousBest, $score->score);
        // Rank of this player's best among everyone's bests on the same board.
        $rank = 1 + Score::query()->where($scope)
            ->selectRaw('user_id, MAX(score) as best')->groupBy('user_id')
            ->havingRaw('MAX(score) > ?', [$best])->get()->count();

        return response()->json([
            'saved' => true,
            'personal_best' => $score->score > $previousBest,
            'best' => $best,
            'rank' => $rank,
        ], 201);
    }

    public function leaderboard(Request $request): View
    {
        $layouts = config('mahjong.layouts');
        $layout = $request->query('layout');
        $daily = null;
        if ($layout === 'daily') {
            $daily = Mahjong::today();
        } elseif (! is_string($layout) || ! isset($layouts[$layout])) {
            $layout = 'turtle';
        }

        $rows = self::top($daily ? Mahjong::dailyLayout($daily) : $layout, $daily, 25);

        $mine = $request->user()?->scores()
            ->when($daily, fn ($q) => $q->whereDate('daily', $daily), fn ($q) => $q->where('layout', $layout)->whereNull('daily'))
            ->latest()->limit(10)->get() ?? collect();

        return view('leaderboard', compact('layouts', 'layout', 'daily', 'rows', 'mine'));
    }

    /**
     * Best score per player on a board. Cached, and flushed whenever a score lands.
     *
     * @return array<int, array{id:int,name:string,best:int,fastest:int,wins:int}>
     */
    public static function top(string $layout, ?string $daily, int $limit): array
    {
        $rows = Cache::remember(self::cacheKey($layout, $daily), 300, fn () => DB::table('scores')
            ->join('users', 'users.id', '=', 'scores.user_id')
            ->when($daily,
                fn ($q) => $q->whereDate('scores.daily', $daily),
                fn ($q) => $q->where('scores.layout', $layout)->whereNull('scores.daily'))
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id as id, users.name as name, MAX(scores.score) as best, MIN(scores.duration) as fastest, COUNT(*) as wins')
            ->orderByDesc('best')
            ->orderBy('fastest')
            ->limit(25)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all());

        return array_slice($rows, 0, $limit);
    }

    public static function cacheKey(string $layout, ?string $daily): string
    {
        return $daily ? "leaderboard:daily:$daily" : "leaderboard:$layout";
    }
}
