<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertMonitoringPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled' => [
                'sometimes',
                'boolean',
            ],

            'profile' => [
                'sometimes',
                'string',
                Rule::in([
                    'discovery',
                    'standard',
                    'deep',
                ]),
            ],

            /*
             * Monitoring is intentionally bounded.
             * V1 does not support rapid-fire scheduled scanning.
             */
            'interval_minutes' => [
                'sometimes',
                'integer',
                'min:15',
                'max:43200',
            ],

            'configuration' => [
                'sometimes',
                'nullable',
                'array',
            ],
        ];
    }
}
