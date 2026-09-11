<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MonitoringChangeEvent;
use App\Models\MonitoringNotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringHistoryController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $events = MonitoringChangeEvent::query()
            ->where('user_id', $request->user()->id)
            ->with([
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
                'payload' => $event->payload,
                'detected_at' => $event->detected_at,

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

    public function event(
        Request $request,
        MonitoringChangeEvent $event,
    ): JsonResponse {
        /*
         * Do not expose another user's event. Returning 404 rather than
         * 403 also avoids confirming that the foreign event exists.
         */
        if ($event->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Monitoring event not found.',
            ], 404);
        }

        $event->load([
            'target:id,user_id,name,url,hostname',
            'assessment:id,target_id,profile,status,queued_at,started_at,completed_at',
            'previousAssessment:id,target_id,profile,status,queued_at,started_at,completed_at',
        ]);

        $deliveries = MonitoringNotificationDelivery::query()
            ->where('user_id', $request->user()->id)
            ->where('change_event_id', $event->id)
            ->latest('created_at')
            ->get()
            ->map(fn (MonitoringNotificationDelivery $delivery) => [
                'id' => $delivery->id,
                'channel' => $delivery->channel,
                'status' => $delivery->status,
                'recipient' => $delivery->recipient,
                'attempt_count' => (int) $delivery->attempt_count,
                'processing_at' => $delivery->processing_at,
                'sent_at' => $delivery->sent_at,
                'failed_at' => $delivery->failed_at,
                'failure_class' => $delivery->failure_class,
                'created_at' => $delivery->created_at,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'fingerprint' => $event->fingerprint,
                    'payload' => $event->payload,
                    'detected_at' => $event->detected_at,

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
                            'queued_at' => $event->assessment->queued_at,
                            'started_at' => $event->assessment->started_at,
                            'completed_at' => $event->assessment->completed_at,
                        ]
                        : null,

                    'previous_assessment' => $event->previousAssessment
                        ? [
                            'id' => $event->previousAssessment->id,
                            'profile' => $event->previousAssessment->profile,
                            'status' => $event->previousAssessment->status,
                            'queued_at' => $event->previousAssessment->queued_at,
                            'started_at' => $event->previousAssessment->started_at,
                            'completed_at' => $event->previousAssessment->completed_at,
                        ]
                        : null,
                ],

                'deliveries' => $deliveries,

                /*
                 * Absence is an observation signal only.
                 * It must not be represented as workflow resolution.
                 */
                'semantics' => [
                    'absence_means_resolved' => false,
                    'no_longer_detected_is_observation' => true,
                ],
            ],
        ]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $deliveries = MonitoringNotificationDelivery::query()
            ->where('user_id', $request->user()->id)
            ->with([
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
}
