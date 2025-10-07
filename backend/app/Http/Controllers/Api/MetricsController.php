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

    /**
     * @OA\Get(
     *     path="/metrics/health",
     *     summary="Get system health status",
     *     tags={"Metrics"},
     *     @OA\Response(
     *         response=200,
     *         description="System is healthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2023-10-01T12:00:00Z"),
     *             @OA\Property(property="services", type="object",
     *                 @OA\Property(property="database", type="string", example="ok"),
     *                 @OA\Property(property="cache", type="string", example="ok")
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/metrics",
     *     summary="Get application metrics",
     *     tags={"Metrics"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="users", type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="online", type="integer", example=25)
     *             ),
     *             @OA\Property(property="chats", type="object",
     *                 @OA\Property(property="total", type="integer", example=50)
     *             ),
     *             @OA\Property(property="messages", type="object",
     *                 @OA\Property(property="total", type="integer", example=1000),
     *                 @OA\Property(property="today", type="integer", example=100),
     *                 @OA\Property(property="sent", type="integer", example=5000),
     *                 @OA\Property(property="avg_send_duration_ms", type="number", format="float", example=150.5)
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2023-10-01T12:00:00Z")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
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