<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->latest('id')
            ->paginate(50);

        return response()->json($logs);
    }
}
