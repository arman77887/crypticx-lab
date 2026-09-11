<?php

namespace App\Http\Requests;

use App\Models\MonitoringNotificationPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertMonitoringNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'email_enabled' => [
                'sometimes',
                'boolean',
            ],

            'event_types' => [
                'sometimes',
                'array',
                'max:' . count(
                    MonitoringNotificationPreference::EVENT_TYPES
                ),
            ],

            'event_types.*' => [
                'string',
                'distinct',
                Rule::in(
                    MonitoringNotificationPreference::EVENT_TYPES
                ),
            ],

            'minimum_risk_delta' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
