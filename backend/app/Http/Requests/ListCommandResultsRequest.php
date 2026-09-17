<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCommandResultsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
