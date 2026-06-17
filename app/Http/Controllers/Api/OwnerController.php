<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class OwnerController extends Controller
{
    public function index(Request $request)
    {
        $query = Owner::withCount('villas');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
        }

        $perPage = min((int) $request->input('per_page', 20), 999);
        return response()->json($query->orderBy('name')->paginate($perPage));
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

    public function show(Owner $owner)
    {
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
