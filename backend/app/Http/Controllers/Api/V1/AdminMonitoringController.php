<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MonitoringChangeEvent;
use App\Models\MonitoringNotificationDelivery;
use App\Models\MonitoringPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMonitoringController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return $this->forbidden();
        }

        $now = now();

        $policies = MonitoringPolicy::query()
            ->with([
                'user:id,name,email',
                'target:id,user_id,name,url,hostname,status,authorization_confirmed',
                'lastAssessment:id,target_id,profile,status,queued_at,started_at,completed_at',
            ])
            ->orderByDesc('enabled')
            ->orderBy('next_run_at')
            ->limit(200)
            ->get()
            ->map(fn (MonitoringPolicy $policy) => [
                'id' => $policy->id,
                'user_id' => $policy->user_id,
                'target_id' => $policy->target_id,
                'enabled' => (bool) $policy->enabled,
                'profile' => $policy->profile,
                'interval_minutes' => (int) $policy->interval_minutes,
                'last_scheduled_at' => $policy->last_scheduled_at,
                'next_run_at' => $policy->next_run_at,

                'owner' => $policy->user
                    ? [
                        'id' => $policy->user->id,
                        'name' => $policy->user->name,
                        'email' => $policy->user->email,
                    ]
                    : null,

                'target' => $policy->target
                    ? [
                        'id' => $policy->target->id,
                        'name' => $policy->target->name,
                        'url' => $policy->target->url,
                        'hostname' => $policy->target->hostname,
                        'status' => $policy->target->status,
                        'authorization_confirmed' =>
                            (bool) $policy->target->authorization_confirmed,
                    ]
                    : null,

                'last_assessment' => $policy->lastAssessment
                    ? [
                        'id' => $policy->lastAssessment->id,
                        'profile' => $policy->lastAssessment->profile,
                        'status' => $policy->lastAssessment->status,
                        'queued_at' => $policy->lastAssessment->queued_at,
                        'started_at' => $policy->lastAssessment->started_at,
                        'completed_at' => $policy->lastAssessment->completed_at,
                    ]
                    : null,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_policies' => MonitoringPolicy::query()->count(),

                    'active_policies' => MonitoringPolicy::query()
                        ->where('enabled', true)
                        ->count(),

                    'disabled_policies' => MonitoringPolicy::query()
                        ->where('enabled', false)
                        ->count(),

                    'due_policies' => MonitoringPolicy::query()
                        ->where('enabled', true)
                        ->whereNotNull('next_run_at')
                        ->where('next_run_at', '<=', $now)
                        ->count(),

                    'change_events' =>
                        MonitoringChangeEvent::query()->count(),

                    'pending_deliveries' =>
                        MonitoringNotificationDelivery::query()
                            ->where('status', 'pending')
                            ->count(),

                    'processing_deliveries' =>
                        MonitoringNotificationDelivery::query()
                            ->where('status', 'processing')
                            ->count(),

                    'sent_deliveries' =>
                        MonitoringNotificationDelivery::query()
                            ->where('status', 'sent')
                            ->count(),

                    'failed_deliveries' =>
                        MonitoringNotificationDelivery::query()
                            ->where('status', 'failed')
                            ->count(),
                ],

                'policies' => $policies,
                'generated_at' => $now,
            ],
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return $this->forbidden();
        }

        $events = MonitoringChangeEvent::query()
            ->with([
                'user:id,name,email',
                'target:id,user_id,name,url,hostname',
                'assessment:id,target_id,profile,status,completed_at',
                'previousAssessment:id,target_id,profile,status,completed_at',
            ])
            ->latest('detected_at')
            ->limit(100)
            ->get()
            ->map(fn (MonitoringChangeEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'fingerprint' => $event->fingerprint,
                'detected_at' => $event->detected_at,
                'payload' => $event->payload,

                'owner' => $event->user
                    ? [
                        'id' => $event->user->id,
                        'name' => $event->user->name,
                        'email' => $event->user->email,
                    ]
                    : null,

                'target' => $event->target
                    ? [
                        'id' => $event->target->id,
                        'name' => $event->target->name,
                        'url' => $event->target->url,
                        'hostname' => $event->target->hostname,
                    ]
                    : null,

                'assessment' => $event->assessment
                    ? [
                        'id' => $event->assessment->id,
                        'profile' => $event->assessment->profile,
                        'status' => $event->assessment->status,
                        'completed_at' => $event->assessment->completed_at,
                    ]
                    : null,

                'previous_assessment' => $event->previousAssessment
                    ? [
                        'id' => $event->previousAssessment->id,
                        'profile' => $event->previousAssessment->profile,
                        'status' => $event->previousAssessment->status,
                        'completed_at' => $event->previousAssessment->completed_at,
                    ]
                    : null,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events,
                'generated_at' => now(),
            ],
        ]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return $this->forbidden();
        }

        $deliveries = MonitoringNotificationDelivery::query()
            ->with([
                'user:id,name,email',
                'changeEvent:id,user_id,target_id,assessment_id,event_type,fingerprint,detected_at',
                'changeEvent.target:id,user_id,name,url,hostname',
            ])
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (MonitoringNotificationDelivery $delivery) => [
                'id' => $delivery->id,
                'change_event_id' => $delivery->change_event_id,
                'channel' => $delivery->channel,
                'status' => $delivery->status,
                'recipient' => $delivery->recipient,
                'attempt_count' => (int) $delivery->attempt_count,
                'processing_at' => $delivery->processing_at,
                'sent_at' => $delivery->sent_at,
                'failed_at' => $delivery->failed_at,
                'failure_class' => $delivery->failure_class,
                'created_at' => $delivery->created_at,

                'owner' => $delivery->user
                    ? [
                        'id' => $delivery->user->id,
                        'name' => $delivery->user->name,
                        'email' => $delivery->user->email,
                    ]
                    : null,

                'event' => $delivery->changeEvent
                    ? [
                        'id' => $delivery->changeEvent->id,
                        'event_type' => $delivery->changeEvent->event_type,
                        'fingerprint' => $delivery->changeEvent->fingerprint,
                        'detected_at' => $delivery->changeEvent->detected_at,

                        'target' => $delivery->changeEvent->target
                            ? [
                                'id' => $delivery->changeEvent->target->id,
                                'name' => $delivery->changeEvent->target->name,
                                'url' => $delivery->changeEvent->target->url,
                                'hostname' => $delivery->changeEvent->target->hostname,
                            ]
                            : null,
                    ]
                    : null,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'deliveries' => $deliveries,
                'generated_at' => now(),
            ],
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
        ], 403);
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
