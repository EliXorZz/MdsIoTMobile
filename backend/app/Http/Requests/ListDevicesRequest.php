<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListDevicesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'online'   => ['nullable', 'boolean'],
            'room_id'  => ['nullable', 'string'],
        ];
    }
}
