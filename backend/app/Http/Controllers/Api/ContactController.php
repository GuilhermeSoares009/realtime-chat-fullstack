<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * @OA\Schema(
 * schema="Contact",
 * type="object",
 * properties={
 * @OA\Property(property="id", type="integer", example=2),
 * @OA\Property(property="name", type="string", example="Alice Smith"),
 * @OA\Property(property="email", type="string", example="alice@example.com"),
 * @OA\Property(property="avatar", type="string", nullable=true, example="null"),
 * @OA\Property(property="bio", type="string", nullable=true, example="Software Developer."),
 * @OA\Property(property="is_online", type="boolean", example=true),
 * @OA\Property(property="last_seen_at", type="string", format="date-time", nullable=true)
 * }
 * )
 */

class ContactController extends Controller
{

    /**
     * @OA\Get(
     * path="/contacts",
     * summary="List user contacts",
     * tags={"Contacts"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page (default: 50)",
     * @OA\Schema(type="integer", example=50)
     * ),
     * @OA\Response(
     * response=200,
     * description="List of contacts successfully retrieved",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Contact")),
     * @OA\Property(property="links", type="object"),
     * @OA\Property(property="meta", type="object")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $userId = $request->user()->id;

        $cacheKey = "user:{$userId}:contacts:page:{$page}:per_page:{$perPage}";

        $contacts = Cache::remember($cacheKey, 600, function () use ($request, $perPage, $userId) {
            $contactIds = $request->user()
                ->chats()
                ->with('users')
                ->get()
                ->pluck('users')
                ->flatten()
                ->pluck('id')
                ->unique()
                ->reject(fn($id) => $id === $userId);

            return User::whereIn('id', $contactIds)
                ->select('id', 'name', 'email', 'avatar', 'bio', 'is_online', 'last_seen_at')
                ->orderBy('name')
                ->paginate($perPage);
        });

        $contacts->getCollection()->transform(function ($user) {
            $onlineStatus = Redis::get("user:online:{$user->id}");
            if ($onlineStatus) {
                $status = json_decode($onlineStatus, true);
                $user->is_online = true;
                $user->last_seen_at = $status['last_seen'];
            } else {
                $user->is_online = false;
            }
            return $user;
        });

        return response()->json($contacts);
    }

    /**
     * @OA\Get(
     * path="/contacts/search",
     * summary="Search for users/contacts",
     * tags={"Contacts"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="q",
     * in="query",
     * required=false,
     * description="Search term (name or email)",
     * @OA\Schema(type="string", example="alice")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page (default: 20)",
     * @OA\Schema(type="integer", example=20)
     * ),
     * @OA\Response(
     * response=200,
     * description="List of users successfully retrieved",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Contact")),
     * @OA\Property(property="links", type="object"),
     * @OA\Property(property="meta", type="object")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $perPage = $request->input('per_page', 20);

        $users = User::where('id', '!=', $request->user()->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->select('id', 'name', 'email', 'avatar', 'bio', 'is_online', 'last_seen_at')
            ->paginate($perPage);

        return response()->json($users);
    }
}
