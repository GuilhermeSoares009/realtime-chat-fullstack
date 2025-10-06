<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Metrics;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{

    public function health()
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbStatus,
                'cache' => cache()->store()->getStore() ? 'ok' : 'error',
            ],
        ]);
    }

    public function metrics(Request $request)
    {
        if (!$request->user() || $request->user()->email !== 'admin@test.com') {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'users' => [
                'total' => User::count(),
                'online' => User::where('is_online', true)->count(),
            ],
            'chats' => [
                'total' => Chat::count(),
            ],
            'messages' => [
                'total' => Message::count(),
                'today' => Message::whereDate('created_at', today())->count(),
                'sent' => Metrics::get('messages.sent'),
                'avg_send_duration_ms' => Metrics::getAvgTiming('messages.send_duration'),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}