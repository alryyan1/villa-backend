<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'ok', 'message' => 'Villa API']);
});

// Fallback for hosts where the public/storage symlink is missing or was
// stripped by a zip-based deploy — serves storage/app/public files directly.
Route::get('/storage/{path}', function (string $path) {
    $base = realpath(storage_path('app/public'));
    $real = realpath(storage_path('app/public/' . $path));

    if (!$base || !$real || !str_starts_with($real, $base)) {
        abort(404);
    }

    return response()->file($real);
})->where('path', '.*');
