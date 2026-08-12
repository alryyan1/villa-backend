<?php

namespace App\Http\Controllers\Api\OwnerPortal;

use App\Http\Controllers\Controller;
use App\Models\Villa;
use Illuminate\Http\Request;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        $villaIds = $request->user()->ownedVillaIds();

        $villas = Villa::whereIn('id', $villaIds)
            ->orderBy('name')
            ->get();

        return response()->json($villas);
    }

    public function show(Request $request, Villa $villa)
    {
        $villaIds = $request->user()->ownedVillaIds();

        if (!in_array($villa->id, $villaIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($villa);
    }
}
