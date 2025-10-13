<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Schema(
 * schema="ChatListUser",
 * type="object",
 * properties={
 * @OA\Property(property="id", type="integer", example=2),
 * @OA\Property(property="name", type="string", example="Alice Smith"),
 * @OA\Property(property="email", type="string", example="alice@example.com"),
 * @OA\Property(property="avatar", type="string", nullable=true, example="null"),
 * @OA\Property(property="is_online", type="boolean", example=true),
 * @OA\Property(property="last_seen_at", type="string", format="date-time", nullable=true)
 * }
 * )
 * * @OA\Schema(
 * schema="ChatListItem",
 * type="object",
 * allOf={
 * @OA\Schema(ref="#/components/schemas/Chat"),
 * @OA\Schema(
 * properties={
 * @OA\Property(property="users", type="array", @OA\Items(ref="#/components/schemas/ChatListUser"), description="Outros usuários no chat."),
 * @OA\Property(property="last_message", type="object", ref="#/components/schemas/Message", nullable=true),
 * @OA\Property(property="unread_count", type="integer", example=5, description="Número de mensagens não lidas."),
 * }
 * )
 * }
 * )
 *
 * @OA\Schema(
 * schema="Chat",
 * type="object",
 * properties={
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="type", type="string", enum={"direct", "group"}, example="direct"),
 * @OA\Property(property="created_at", type="string", format="date-time"),
 * @OA\Property(property="updated_at", type="string", format="date-time")
 * }
 * )
 */

class ChatController extends Controller
{

    /**
     * @OA\Get(
     * path="/chats",
     * summary="List all user chats",
     * tags={"Chats"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page (default: 20)",
     * @OA\Schema(type="integer", example=20)
     * ),
     * @OA\Response(
     * response=200,
     * description="List of chats successfully retrieved",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ChatListItem")),
     * @OA\Property(property="links", type="object"),
     * @OA\Property(property="meta", type="object")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $userId = $request->user()->id;

        $cacheKey = "user:{$userId}:chats:page:{$page}:per_page:{$perPage}";

        $chats = Cache::remember($cacheKey, 120, function () use ($request, $perPage, $userId) {
            return $request->user()
                ->chats()
                ->with(['lastMessage.user', 'users' => function ($query) use ($userId) {
                    $query->where('users.id', '!=', $userId)
                        ->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.is_online', 'users.last_seen_at');
                }])
                ->withCount(['messages as unread_count' => function ($query) use ($userId) {
                    $query->where('user_id', '!=', $userId)
                        ->where('is_read', false);
                }])
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage);
        });

        $chats->getCollection()->transform(function ($chat) {
            $chat->users->transform(function ($user) {
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
            return $chat;
        });

        return response()->json($chats);
    }

    /**
     * @OA\Post(
     * path="/chats/direct",
     * summary="Get or create a direct chat",
     * tags={"Chats"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"user_id"},
     * @OA\Property(property="user_id", type="integer", example=5, description="ID of the user to chat with")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Chat retrieved or created successfully",
     * @OA\JsonContent(
     * @OA\Property(property="chat", type="object", ref="#/components/schemas/ChatListItem")
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error or cannot chat with self",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Cannot create chat with yourself")
     * )
     * )
     * )
     */
    public function getOrCreateDirectChat(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $otherUserId = $validated['user_id'];
        $currentUserId = $request->user()->id;

        if ($otherUserId == $currentUserId) {
            return response()->json([
                'message' => 'Cannot create chat with yourself',
            ], 422);
        }

        $chat = Chat::where('type', 'direct')
            ->whereHas('users', function ($query) use ($currentUserId) {
                $query->where('users.id', $currentUserId);
            })
            ->whereHas('users', function ($query) use ($otherUserId) {
                $query->where('users.id', $otherUserId);
            })
            ->first();

        if (!$chat) {
            $chat = DB::transaction(function () use ($currentUserId, $otherUserId) {
                $chat = Chat::create([
                    'type' => 'direct',
                ]);

                $chat->users()->attach([$currentUserId, $otherUserId], [
                    'joined_at' => now(),
                ]);

                return $chat;
            });

            Cache::tags(["user:{$currentUserId}:contacts"])->flush();
            Cache::tags(["user:{$otherUserId}:contacts"])->flush();
            Cache::tags(["user:{$currentUserId}:chats"])->flush();
            Cache::tags(["user:{$otherUserId}:chats"])->flush();
        }

        $chat->load(['users' => function ($query) use ($currentUserId) {
            $query->where('users.id', '!=', $currentUserId);
        }, 'lastMessage']);

        return response()->json([
            'chat' => $chat,
        ]);
    }

    /**
     * @OA\Get(
     * path="/chats/{id}",
     * summary="Get a specific chat by ID",
     * tags={"Chats"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the chat",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Chat found successfully",
     * @OA\JsonContent(
     * @OA\Property(property="chat", type="object", ref="#/components/schemas/ChatListItem")
     * )
     * ),
     * @OA\Response(response=404, description="Chat not found or user not a member")
     * )
     */
    public function show(Request $request, $id)
    {
        $chat = $request->user()
            ->chats()
            ->with(['users' => function ($query) use ($request) {
                $query->where('users.id', '!=', $request->user()->id);
            }, 'lastMessage'])
            ->findOrFail($id);

        return response()->json([
            'chat' => $chat,
        ]);
    }

    /**
     * @OA\Delete(
     * path="/chats/{id}",
     * summary="Leave or delete a chat",
     * tags={"Chats"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the chat",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=204,
     * description="Chat successfully left or deleted (No Content)",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Chat deleted successfully")
     * )
     * ),
     * @OA\Response(response=404, description="Chat not found or user not a member")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $chat = $request->user()->chats()->findOrFail($id);

        // For direct chats, just remove the user from the chat
        $chat->users()->detach($request->user()->id);

        // If no users left, delete the chat
        if ($chat->users()->count() === 0) {
            $chat->delete();
        }

        return response()->json([
            'message' => 'Chat deleted successfully',
        ], 204);
    }

    /**
     * @OA\Put(
     * path="/chats/{id}/read",
     * summary="Mark all messages in a chat as read",
     * tags={"Chats"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the chat",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Chat marked as read successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Chat marked as read")
     * )
     * ),
     * @OA\Response(response=404, description="Chat not found or user not a member")
     * )
     */
    public function markAsRead(Request $request, $id)
    {
        $chat = $request->user()->chats()->findOrFail($id);

        // Update last_read_at in pivot
        $request->user()->chats()->updateExistingPivot($chat->id, [
            'last_read_at' => now(),
        ]);

        // Mark all messages as read
        $chat->messages()
            ->where('user_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Chat marked as read',
        ]);
    }
}
