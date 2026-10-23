<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 50), 100));

        $event = $request->query('event');
        $subjectType = $request->query('subject_type');
        $from = $request->query('from');
        $to = $request->query('to');

        $page = AuditEvent::query()
            ->where('user_id', $request->user()->getKey())
            ->when(is_string($event) && $event !== '', fn ($query) => $query->where('event', $event))
            ->when(is_string($subjectType) && $subjectType !== '', fn ($query) => $query->where('subject_type', $subjectType))
            ->when(is_string($from) && $from !== '', fn ($query) => $query->where('created_at', '>=', $from))
            ->when(is_string($to) && $to !== '', fn ($query) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return AuditEventResource::collection($page)->response();
    }
}
