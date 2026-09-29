<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'target_id' => [
                'required',
                'uuid',
                Rule::exists('targets', 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'profile' => [
                'required',
                'string',
                Rule::in([
                    'discovery',
                    'standard',
                    'deep',
                ]),
            ],
            'configuration' => ['nullable', 'array'],
            'configuration.scan_type' => [
                'nullable',
                'string',
                Rule::in([
                    'web_security',
                    'ssl_tls',
                    'dns_intelligence',
                    'api_security',
                ]),
            ],
        ];
    }
}
