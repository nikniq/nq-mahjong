<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Support\Mahjong;
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
        ]);
        $layout = $request->string('layout')->value();

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
        $previousBest = (int) $user->scores()->where('layout', $layout)->max('score');

        $score = $user->scores()->create($data + ['layout' => $layout]);
        Cache::forget("leaderboard:$layout");

        return response()->json([
            'saved' => true,
            'personal_best' => $score->score > $previousBest,
            'best' => max($previousBest, $score->score),
        ], 201);
    }

    public function leaderboard(Request $request): View
    {
        $layouts = config('mahjong.layouts');
        $layout = $request->query('layout');
        if (! is_string($layout) || ! isset($layouts[$layout])) {
            $layout = 'turtle';
        }

        // Best score per player. Cached, and flushed whenever a score lands.
        $rows = Cache::remember("leaderboard:$layout", 300, fn () => DB::table('scores')
            ->join('users', 'users.id', '=', 'scores.user_id')
            ->where('scores.layout', $layout)
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id as id, users.name as name, MAX(scores.score) as best, MIN(scores.duration) as fastest, COUNT(*) as wins')
            ->orderByDesc('best')
            ->orderBy('fastest')
            ->limit(25)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all());

        $mine = $request->user()
            ?->scores()->where('layout', $layout)->latest()->limit(10)->get() ?? collect();

        return view('leaderboard', compact('layouts', 'layout', 'rows', 'mine'));
    }
}
