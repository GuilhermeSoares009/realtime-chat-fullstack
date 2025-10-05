<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{

    public function index(Request $request, $chatId)
    {
        $chat = $request->user()->chats()->findOrFail($chatId);

        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');

        $query = $chat->messages()->with('user:id,name,avatar');

        if ($search) {
            $query->where('content', 'LIKE', "%{$search}%");
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($messages);
    }

    public function store(Request $request, $chatId)
    {
        $chat = $request->user()->chats()->findOrFail($chatId);

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message = DB::transaction(function () use ($chat, $validated, $request) {
            $message = $chat->messages()->create([
                'user_id' => $request->user()->id,
                'content' => $validated['content'],
            ]);

            $chat->update([
                'last_message_id' => $message->id,
                'updated_at' => now(),
            ]);

            return $message;
        });

        $message->load('user:id,name,avatar');

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'message' => $message,
        ], 201);
    }

    public function update(Request $request, $chatId, $messageId)
    {
        $chat = $request->user()->chats()->findOrFail($chatId);

        $message = $chat->messages()
            ->where('id', $messageId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message->update($validated);

        return response()->json([
            'message' => $message,
        ]);
    }

    public function destroy(Request $request, $chatId, $messageId)
    {
        $chat = $request->user()->chats()->findOrFail($chatId);

        $message = $chat->messages()
            ->where('id', $messageId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $message->delete();

        return response()->json([
            'message' => 'Message deleted successfully',
        ], 204);
    }

    public function markAsRead(Request $request, $chatId, $messageId)
    {
        $chat = $request->user()->chats()->findOrFail($chatId);

        $message = $chat->messages()
            ->where('id', $messageId)
            ->where('user_id', '!=', $request->user()->id)
            ->firstOrFail();

        $message->markAsRead();

        broadcast(new MessageRead($message, $request->user()->id))->toOthers();

        return response()->json([
            'message' => 'Message marked as read',
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $perPage = $request->input('per_page', 20);

        $chatIds = $request->user()->chats()->pluck('chats.id');

        $messages = Message::whereIn('chat_id', $chatIds)
            ->where('content', 'LIKE', "%{$query}%")
            ->with(['user:id,name,avatar', 'chat'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($messages);
    }

    public function typing(Request $request, $chatId)
    {
        $chat = $request->user()->chats()->findOrFail($chatId);

        $validated = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        broadcast(new UserTyping(
            $chat->id,
            $request->user()->id,
            $request->user()->name,
            $validated['is_typing']
        ))->toOthers();

        return response()->json(['message' => 'Typing status sent']);
    }
}
