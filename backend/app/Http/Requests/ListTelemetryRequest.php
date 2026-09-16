<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListTelemetryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'bucket'   => ['nullable', 'string', 'in:1m,5m,30m,1h,1d'],
        ];
    }
}
