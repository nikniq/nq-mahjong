<?php

namespace App\Support;

class Mahjong
{
    /** Par time in seconds for a layout. */
    public static function par(string $layout): int
    {
        return self::pairs($layout) * (int) config('mahjong.scoring.par_per_pair');
    }

    public static function pairs(string $layout): int
    {
        return intdiv((int) config("mahjong.layouts.$layout.tiles"), 2);
    }

    /** Highest score that is physically reachable on a layout. */
    public static function maxScore(string $layout): int
    {
        $s = config('mahjong.scoring');

        return (int) (self::pairs($layout) * $s['pair'] * $s['combo_max']
            + self::par($layout) * $s['time_bonus']);
    }

    /** Fastest plausible clear, in seconds (two clicks per pair). */
    public static function minDuration(string $layout): int
    {
        return (int) ceil(self::pairs($layout) * 0.4);
    }

    /** Config payload handed to the browser. */
    public static function clientConfig(): array
    {
        $layouts = [];
        foreach (config('mahjong.layouts') as $key => $layout) {
            $layouts[$key] = $layout + ['par' => self::par($key)];
        }

        return ['layouts' => $layouts, 'scoring' => config('mahjong.scoring')];
    }
}
