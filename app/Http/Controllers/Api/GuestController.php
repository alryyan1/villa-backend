<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $query = Guest::withCount('bookings');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('id_number', 'like', "%{$request->search}%");
        }

        return response()->json($query->orderBy('id','desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'required|string|max:20',
            'id_number'   => 'required|string|max:50',
            'nationality' => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
        ]);

        $guest = Guest::create($validated);
        ActivityLogService::log('create_guest', 'Guest', $guest->id, ['name' => $guest->name]);

        return response()->json($guest->loadCount('bookings'), 201);
    }

    public function show(Guest $guest)
    {
        return response()->json($guest->load('bookings.villa')->loadCount('bookings'));
    }

    public function update(Request $request, Guest $guest)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'required|string|max:20',
            'id_number'   => 'required|string|max:50',
            'nationality' => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
        ]);

        $guest->update($validated);
        ActivityLogService::log('update_guest', 'Guest', $guest->id, ['name' => $guest->name]);

        return response()->json($guest->loadCount('bookings'));
    }

    public function destroy(Guest $guest)
    {
        ActivityLogService::log('delete_guest', 'Guest', $guest->id, ['name' => $guest->name]);
        $guest->delete();
        return response()->json(['message' => 'Guest deleted successfully.']);
    }
}
