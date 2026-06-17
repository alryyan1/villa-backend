<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public static function log(string $action, string $modelType = null, int $modelId = null, array $details = []): void
    {
        ActivityLog::create([
            'user_id'    => Auth::id(),
            'action'     => $action,
            'model_type' => $modelType,
            'model_id'   => $modelId,
            'details'    => $details,
            'ip_address' => Request::ip(),
        ]);
    }
}
