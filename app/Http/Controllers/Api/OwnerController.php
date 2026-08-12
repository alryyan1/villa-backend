<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class OwnerController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Owner::withCount('villas')->with(['villas:id,name,owner_id']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->villa_id) {
            $query->whereHas('villas', fn ($q) => $q->where('villas.id', $request->villa_id));
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'notes'           => 'nullable|string',
        ]);

        $owner = Owner::create($validated);
        ActivityLogService::log('create_owner', 'Owner', $owner->id, ['name' => $owner->name]);

        return response()->json($owner->loadCount('villas'), 201);
    }

    public function show(Request $request, Owner $owner)
    {
        if ($request->user()->isOwner()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($owner->load('villas')->loadCount('villas'));
    }

    public function update(Request $request, Owner $owner)
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string',
            'notes'           => 'nullable|string',
        ]);

        $owner->update($validated);
        ActivityLogService::log('update_owner', 'Owner', $owner->id, ['name' => $owner->name]);

        return response()->json($owner->loadCount('villas'));
    }

    /**
     * Staff-only action: create a portal login (User with role=owner) for this
     * Owner and link them. Rejects if the owner already has a login, or if the
     * given email belongs to an existing user — we never silently convert an
     * existing (e.g. staff) account to the owner role.
     */
    public function enableLogin(Request $request, Owner $owner)
    {
        if ($owner->user_id) {
            return response()->json(['message' => 'This owner already has portal login access.'], 422);
        }

        $validated = $request->validate([
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            return response()->json(['message' => 'A user account with this email already exists. Choose a different email.'], 422);
        }

        $user = User::create([
            'name'      => $owner->name,
            'email'     => $validated['email'],
            'password'  => $validated['password'],
            'role'      => 'owner',
            'is_active' => true,
        ]);

        $owner->update(['user_id' => $user->id]);

        ActivityLogService::log('enable_owner_login', 'Owner', $owner->id, [
            'name'  => $owner->name,
            'email' => $user->email,
        ]);

        return response()->json($owner->fresh()->load('user'));
    }

    public function copyPhonesToWhatsApp()
    {
        $updated = Owner::whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($q) {
                $q->whereNull('whatsapp_number')->orWhere('whatsapp_number', '');
            })
            ->update(['whatsapp_number' => \Illuminate\Support\Facades\DB::raw('phone')]);

        return response()->json(['message' => "{$updated} owners updated.", 'updated' => $updated]);
    }

    public function destroy(Owner $owner)
    {
        ActivityLogService::log('delete_owner', 'Owner', $owner->id, ['name' => $owner->name]);
        $owner->delete();
        return response()->json(['message' => 'Owner deleted successfully.']);
    }
}
