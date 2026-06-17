<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
        }
        if ($request->role) {
            $query->where('role', $request->role);
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8',
            'role'      => 'in:admin,manager,staff',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
        ActivityLogService::log('create_user', 'User', $user->id, ['name' => $user->name]);

        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => "sometimes|email|unique:users,email,{$user->id}",
            'password'  => 'sometimes|string|min:8',
            'role'      => 'sometimes|in:admin,manager,staff',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);
        ActivityLogService::log('update_user', 'User', $user->id, ['name' => $user->name]);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        ActivityLogService::log('delete_user', 'User', $user->id, ['name' => $user->name]);
        $user->delete();
        return response()->json(['message' => 'User deleted successfully.']);
    }
}
