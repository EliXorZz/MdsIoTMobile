<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCommandRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['set_ventilation'])],
            'params' => ['required', 'array'],
            'params.enabled' => ['required', 'boolean'],
        ];
    }
}
