<?php

namespace App\Http\Controllers\Api;

use App\Events\UserOnlineStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    
    public function show(Request $request, $id = null)
    {
        $userId = $id ?? $request->user()->id;
        
        $user = User::findOrFail($userId);

        return response()->json([
            'user' => $user,
        ]);
    }

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
            'avatar' => 'nullable|string', // Base64 ou URL
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

    public function destroy(Request $request)
    {
        $user = $request->user();
        
        // Delete all user's tokens
        $user->tokens()->delete();
        
        // Delete user
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ], 204);
    }

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

    public function updateOnlineStatus(Request $request)
    {
        $validated = $request->validate([
            'is_online' => 'required|boolean',
        ]);

        $request->user()->updateOnlineStatus($validated['is_online']);

        broadcast(new UserOnlineStatusChanged($request->user()));

        return response()->json([
            'message' => 'Status updated',
        ]);
    }
}