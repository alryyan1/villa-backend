<?php

namespace App\Http\Controllers\Api\OwnerPortal;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    /**
     * Only guests who have a prior booking at one of this owner's villas —
     * exposing the full guest list would leak other owners' tenants.
     */
    public function index(Request $request)
    {
        $villaIds = $request->user()->ownedVillaIds();

        $query = Guest::whereHas('bookings', fn ($q) => $q->whereIn('villa_id', $villaIds));

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('id_number', 'like', "%{$request->search}%");
            });
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'phone'        => 'required|string|max:20',
            'country_code' => 'nullable|string|max:4',
            'id_number'    => 'required|string|max:50',
            'nationality'  => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);
        $validated['country_code'] = $validated['country_code'] ?? '968';

        $guest = Guest::create($validated);
        ActivityLogService::log('create_guest', 'Guest', $guest->id, ['name' => $guest->name]);

        return response()->json($guest, 201);
    }
}
