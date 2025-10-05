<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        
        $chats = $request->user()
            ->chats()
            ->with(['lastMessage.user', 'users' => function ($query) use ($request) {
                $query->where('users.id', '!=', $request->user()->id)
                      ->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.is_online', 'users.last_seen_at');
            }])
            ->withCount(['messages as unread_count' => function ($query) use ($request) {
                $query->where('user_id', '!=', $request->user()->id)
                      ->where('is_read', false);
            }])
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        return response()->json($chats);
    }

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
        }

        $chat->load(['users' => function ($query) use ($currentUserId) {
            $query->where('users.id', '!=', $currentUserId);
        }, 'lastMessage']);

        return response()->json([
            'chat' => $chat,
        ]);
    }

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