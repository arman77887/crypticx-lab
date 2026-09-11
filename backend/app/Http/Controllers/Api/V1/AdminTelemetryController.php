<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTelemetryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $limit = min(
            max((int) $request->integer('limit', 100), 1),
            200
        );

        $events = AuditLog::query()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'category' => $event->category,
                'method' => $event->method,
                'route' => $event->route,
                'ip_address' => $event->ip_address,
                'user_agent' => $event->user_agent,
                'resource_type' => $event->resource_type,
                'resource_id' => $event->resource_id,

                'user' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                ] : null,

                'metadata' => $event->metadata,
                'created_at' => $event->created_at,
            ]);

        $activity = ActivityEvent::query()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'ip_address' => $event->ip_address,
                'country_code' => $event->country_code,
                'country_name' => $event->country_name,
                'region' => $event->region,
                'city' => $event->city,
                'device_type' => $event->device_type,
                'browser' => $event->browser,
                'platform' => $event->platform,

                'user' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                ] : null,

                'metadata' => $event->metadata,
                'created_at' => $event->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'audit_logs' => $events,
                'activity_events' => $activity,
                'summary' => [
                    'audit_logs_returned' => $events->count(),
                    'activity_events_returned' => $activity->count(),
                    'limit' => $limit,
                ],
                'generated_at' => now(),
            ],
        ]);
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $request->user()
            ->roles()
            ->whereIn('slug', [
                'owner',
                'administrator',
            ])
            ->exists();
    }
}
