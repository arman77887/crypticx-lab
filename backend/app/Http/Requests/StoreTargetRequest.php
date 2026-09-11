<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'url' => [
                'required',
                'string',
                'url:http,https',
                'max:2048',
            ],
            'authorization_confirmed' => ['required', 'accepted'],
            'authorization_method' => ['required', 'string', 'max:80'],
        ];
    }
}
