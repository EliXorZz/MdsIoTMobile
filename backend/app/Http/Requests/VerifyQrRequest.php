<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyQrRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:64'],
            'issued_at' => ['required', 'integer'],
            'sig' => ['required', 'string', 'size:64'],
        ];
    }
}
