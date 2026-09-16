<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListTelemetryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
