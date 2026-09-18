<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'category' => [
                'required',
                Rule::in([
                    'general',
                    'security',
                    'partnership',
                    'support',
                ]),
            ],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],

            // Honeypot. Normal users never fill this field.
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }
}
