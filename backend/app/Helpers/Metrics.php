<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Metrics
{

    public static function increment(string $key, int $amount = 1): void
    {
        $currentValue = Cache::get($key, 0);
        Cache::put($key, $currentValue + $amount, now()->addDay());
        
        Log::channel('chat')->debug("Metric incremented: {$key}", [
            'key' => $key,
            'amount' => $amount,
            'new_value' => $currentValue + $amount,
        ]);
    }

    public static function timing(string $key, float $milliseconds): void
    {
        $timings = Cache::get("{$key}_timings", []);
        $timings[] = $milliseconds;
        
        if (count($timings) > 1000) {
            $timings = array_slice($timings, -1000);
        }
        
        Cache::put("{$key}_timings", $timings, now()->addDay());
        
        Log::channel('chat')->debug("Metric timing: {$key}", [
            'key' => $key,
            'duration_ms' => $milliseconds,
            'avg' => round(array_sum($timings) / count($timings), 2),
        ]);
    }

    public static function get(string $key): mixed
    {
        return Cache::get($key, 0);
    }

    public static function getAvgTiming(string $key): float
    {
        $timings = Cache::get("{$key}_timings", []);
        
        if (empty($timings)) {
            return 0;
        }
        
        return round(array_sum($timings) / count($timings), 2);
    }
}