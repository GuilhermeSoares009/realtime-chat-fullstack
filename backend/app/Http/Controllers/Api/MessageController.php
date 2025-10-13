<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Helpers\Metrics;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMessageNotification;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * @OA\Schema(
 *     schema="Message",
 *     type="object",
 *     title="Message",
 *     required={"id", "content", "user_id", "chat_id", "created_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="content", type="string", example="This is a message content."),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="chat_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-10-01T12:34:56Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-10-01T12:34:56Z"),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="avatar", type="string", format="url", example="http://example.com/avatar.jpg")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="TypingStatus",
 *     type="object",
 *     title="TypingStatus",
 *     required={"is_typing"},
 *     @OA\Property(property="is_typing", type="boolean", example=true, description="Indicates if the user is typing")
 * )
 */
class MessageController extends Controller
{

    /**
     * @OA\Get(
     * path="/chats/{chatId}/messages",
     * summary="List messages in a chat",
     * tags={"Messages"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="chatId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID do chat"
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * @OA\Schema(type="integer", example=50),
     * description="Número de mensagens por página"
     * ),
     * @OA\Parameter(
     * name="search",
     * in="query",
     * required=false,
     * @OA\Schema(type="string", example="como vai"),
     * description="Busca por conteúdo na mensagem"
     * ),
     * @OA\Response(
     * response=200,
     * description="Lista de mensagens paginada",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Message")),
     * @OA\Property(property="links", type="object"),
     * @OA\Property(property="meta", type="object")
     * )
     * ),
     * @OA\Response(response=404, description="Chat não encontrado ou usuário não é participante")
     * )
     */
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

    /**
     * @OA\Post(
     * path="/chats/{chatId}/messages",
     * summary="Send a new message to a chat",
     * tags={"Messages"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="chatId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID do chat"
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"content"},
     * @OA\Property(property="content", type="string", example="Esta é uma nova mensagem."),
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Message sent successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", ref="#/components/schemas/Message")
     * )
     * ),
     * @OA\Response(response=404, description="Chat não encontrado ou usuário não é participante"),
     * @OA\Response(response=422, description="Validation error (e.g., empty content)")
     * )
     */
    public function store(Request $request, $chatId)
    {
        $startTime = microtime(true);

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

        ProcessMessageNotification::dispatch($message);

        Metrics::increment('messages.sent');
        Metrics::timing('messages.send_duration', (microtime(true) - $startTime) * 1000);

        return response()->json([
            'message' => $message,
        ], 201);
    }

    /**
     * @OA\Put(
     * path="/chats/{chatId}/messages/{messageId}",
     * summary="Update a message",
     * tags={"Messages"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="chatId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID do chat"
     * ),
     * @OA\Parameter(
     * name="messageId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=10),
     * description="ID da mensagem"
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"content"},
     * @OA\Property(property="content", type="string", example="Esta é a mensagem atualizada."),
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Message updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", ref="#/components/schemas/Message")
     * )
     * ),
     * @OA\Response(response=404, description="Chat ou mensagem não encontrado, ou usuário não é o autor"),
     * @OA\Response(response=422, description="Validation error (e.g., empty content)")
     * )
     */
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

    /**
     * @OA\Delete(
     * path="/chats/{chatId}/messages/{messageId}",
     * summary="Delete a message",
     * tags={"Messages"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="chatId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID do chat"
     * ),
     * @OA\Parameter(
     * name="messageId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=10),
     * description="ID da mensagem"
     * ),
     * @OA\Response(response=204, description="Message deleted successfully"),
     * @OA\Response(response=404, description="Chat ou mensagem não encontrado, ou usuário não é o autor")
     * )
     */
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

    /**
     * @OA\Post(
     * path="/chats/{chatId}/messages/{messageId}/read",
     * summary="Mark a message as read",
     * tags={"Messages"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="chatId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID do chat"
     * ),
     * @OA\Parameter(
     * name="messageId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=10),
     * description="ID da mensagem"
     * ),
     * @OA\Response(
     * response=200,
     * description="Message marked as read",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Message marked as read")
     * )
     * ),
     * @OA\Response(response=404, description="Chat ou mensagem não encontrado, ou usuário é o autor")
     * )
     */
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

    /**
     * @OA\Get(
     * path="/messages/search",
     * summary="Search messages across all chats",
     * tags={"Messages"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="q",
     * in="query",
     * required=true,
     * @OA\Schema(type="string", example="palavra-chave"),
     * description="Termo de busca"
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * @OA\Schema(type="integer", example=20),
     * description="Número de resultados por página"
     * ),
     * @OA\Response(
     * response=200,
     * description="Search results paginated",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Message")),
     * @OA\Property(property="links", type="object"),
     * @OA\Property(property="meta", type="object")
     * )
     * ),
     * @OA\Response(response=422, description="Validation error (e.g., missing search term)")
     * )
     */  
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

    /**
     * @OA\Post(
     * path="/chats/{chatId}/typing",
     * summary="Send typing status in a chat",
     * tags={"Messages"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="chatId",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID do chat"
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(ref="#/components/schemas/TypingStatus")
     * ),
     * @OA\Response(
     * response=200,
     * description="Typing status sent",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Typing status sent")
     * )
     * ),
     * @OA\Response(response=404, description="Chat não encontrado ou usuário não é participante"),
     * @OA\Response(response=422, description="Validation error (e.g., missing or invalid is_typing)")
     * )
     */
    public function typing(Request $request, $chatId)
    {
        $chat = $request->user()->chats()->findOrFail($chatId);

        $validated = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        $userId = $request->user()->id;

        if($validated['is_typing']){
            Redis::setex(
                "chat:{$chatId}:typing:{$userId}",
                5,
                true
            );
        } else {
            Redis::del("chat:{$chatId}:typing:{$userId}");
        }

        broadcast(new UserTyping(
            $chat->id,
            $request->user()->id,
            $request->user()->name,
            $validated['is_typing']
        ))->toOthers();

        return response()->json(['message' => 'Typing status sent']);
    }
}
