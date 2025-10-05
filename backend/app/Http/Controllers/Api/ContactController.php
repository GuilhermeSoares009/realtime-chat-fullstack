<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ContactController extends Controller
{

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        
        $contactIds = $request->user()
            ->chats()
            ->with('users')
            ->get()
            ->pluck('users')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->reject(fn($id) => $id === $request->user()->id);

        $contacts = User::whereIn('id', $contactIds)
            ->select('id', 'name', 'email', 'avatar', 'bio', 'is_online', 'last_seen_at')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($contacts);
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
}