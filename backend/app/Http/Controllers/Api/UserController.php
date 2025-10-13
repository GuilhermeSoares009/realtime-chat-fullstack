<?php

namespace App\Http\Controllers\Api;

use App\Events\UserOnlineStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com")
 * )
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 * )
 * 
 * 
 * @OA\Tag(
 *     name="Usuário",
 *     description="Endpoints relacionados ao usuário"
 * )
 */

class UserController extends Controller
{

    /**
     * @OA\Get(
     * path="/users/{id}",
     * summary="Retorna dados do usuário pelo ID ou do usuário autenticado se ID omitido",
     * tags={"Usuário"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * description="ID do usuário (opcional para o usuário autenticado)",
     * required=false,
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Usuário encontrado",
     * @OA\JsonContent(
     * @OA\Property(property="user", ref="#/components/schemas/User")
     * )
     * ),
     * @OA\Response(response=404, description="Usuário não encontrado")
     * )
     */
    public function show(Request $request, $id = null)
    {
        $userId = $id ?? $request->user()->id;

        $user = User::findOrFail($userId);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * @OA\Put(
     * path="/users",
     * summary="Atualiza os dados do usuário autenticado",
     * tags={"Usuário"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=false,
     * @OA\JsonContent(
     * @OA\Property(property="name", type="string", maxLength=255, example="Novo Nome"),
     * @OA\Property(property="email", type="string", format="email", maxLength=255, example="novo@email.com"),
     * @OA\Property(property="bio", type="string", maxLength=500, nullable=true),
     * @OA\Property(property="avatar", type="string", nullable=true, description="Base64 ou URL"),
     * @OA\Property(property="password", type="string", minLength=8, nullable=true),
     * @OA\Property(property="password_confirmation", type="string", nullable=true)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Usuário atualizado com sucesso",
     * @OA\JsonContent(
     * @OA\Property(property="user", ref="#/components/schemas/User"),
     * @OA\Property(property="message", type="string", example="Profile updated successfully")
     * )
     * ),
     * @OA\Response(response=422, description="Erro de validação")
     * )
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'user' => $user,
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * @OA\Delete(
     * path="/users",
     * summary="Deleta conta do usuário autenticado",
     * tags={"Usuário"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=204, description="Conta deletada com sucesso"),
     * )
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();

        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ], 204);
    }

    /**
     * @OA\Get(
     * path="/users/search",
     * summary="Busca usuários pelo nome ou email",
     * tags={"Usuário"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="q",
     * in="query",
     * description="Termo de busca",
     * required=false,
     * @OA\Schema(type="string", example="john")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="Itens por página",
     * required=false,
     * @OA\Schema(type="integer", default=20)
     * ),
     * @OA\Response(
     * response=200,
     * description="Lista paginada de usuários",
     * @OA\JsonContent(type="object")
     * )
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

    /**
     * @OA\Put(
     * path="/users/online-status",
     * summary="Atualiza status online do usuário autenticado",
     * tags={"Usuário"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"is_online"},
     * @OA\Property(property="is_online", type="boolean", example=true)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Status atualizado",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string")
     * )
     * ),
     * @OA\Response(response=422, description="Erro de validação")
     * )
     */
    public function updateOnlineStatus(Request $request)
    {
        $validated = $request->validate([
            'is_online' => 'required|boolean',
        ]);

        $user = $request->user();

        if ($validated['is_online']) {
            Redis::setex(
                "user:online:{$user->id}",
                300,
                json_encode([
                    'status' => 'online',
                    'last_seen' => now()->toIso8601String()
                ])
            );
        }else{
            Redis::del("user:online:{$user->id}");
        }

        $user->updateOnlineStatus($validated['is_online']);

        broadcast(new UserOnlineStatusChanged($request->user()));

        return response()->json([
            'message' => 'Status updated',
        ]);
    }
}