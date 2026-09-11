<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertMonitoringNotificationPreferenceRequest;
use App\Models\MonitoringNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringNotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $preference = MonitoringNotificationPreference::query()
            ->where('user_id', $request->user()->id)
            ->first();

        /*
         * A missing row means canonical defaults.
         * GET is read-only and must not create database state.
         */
        if (! $preference) {
            return response()->json([
                'success' => true,
                'data' => [
                    'persisted' => false,
                    'email_enabled' => true,
                    'event_types' =>
                        MonitoringNotificationPreference::defaultEventTypes(),
                    'minimum_risk_delta' => 1,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'persisted' => true,
                'id' => $preference->id,
                'email_enabled' => $preference->email_enabled,
                'event_types' => $preference->event_types,
                'minimum_risk_delta' =>
                    $preference->minimum_risk_delta,
                'created_at' => $preference->created_at,
                'updated_at' => $preference->updated_at,
            ],
        ]);
    }

    public function upsert(
        UpsertMonitoringNotificationPreferenceRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $result = DB::transaction(function () use (
            $request,
            $validated
        ): array {
            $existing = MonitoringNotificationPreference::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            $emailEnabled = array_key_exists(
                'email_enabled',
                $validated
            )
                ? (bool) $validated['email_enabled']
                : ($existing?->email_enabled ?? true);

            $eventTypes = array_key_exists(
                'event_types',
                $validated
            )
                ? array_values($validated['event_types'])
                : (
                    $existing?->event_types
                    ?? MonitoringNotificationPreference::defaultEventTypes()
                );

            $minimumRiskDelta = (int) (
                $validated['minimum_risk_delta']
                ?? $existing?->minimum_risk_delta
                ?? 1
            );

            $preference =
                MonitoringNotificationPreference::query()
                    ->updateOrCreate(
                        [
                            'user_id' => $request->user()->id,
                        ],
                        [
                            'email_enabled' => $emailEnabled,
                            'event_types' => $eventTypes,
                            'minimum_risk_delta' =>
                                $minimumRiskDelta,
                        ],
                    );

            return [
                'created' => ! $existing,
                'preference' => $preference->fresh(),
            ];
        });

        $preference = $result['preference'];

        return response()->json([
            'success' => true,
            'message' => $result['created']
                ? 'Monitoring notification preferences created successfully.'
                : 'Monitoring notification preferences updated successfully.',
            'data' => [
                'persisted' => true,
                'id' => $preference->id,
                'email_enabled' => $preference->email_enabled,
                'event_types' => $preference->event_types,
                'minimum_risk_delta' =>
                    $preference->minimum_risk_delta,
                'created_at' => $preference->created_at,
                'updated_at' => $preference->updated_at,
            ],
        ], $result['created'] ? 201 : 200);
    }
}
