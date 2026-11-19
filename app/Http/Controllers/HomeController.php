<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\User;
use App\Support\Mahjong;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $today = Mahjong::today();

        $stats = Cache::remember('home:stats', 600, fn () => [
            'players' => User::count(),
            'cleared' => Score::count(),
            'today' => Score::whereDate('daily', $today)->count(),
        ]);

        return view('home', [
            'stats' => $stats,
            'champions' => ScoreController::top('turtle', null, 5),
            'dailyTop' => ScoreController::top(Mahjong::dailyLayout($today), $today, 3),
        ]);
    }
}
