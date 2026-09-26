<?php

namespace App\Http\Requests\Uploads;

use App\Http\Requests\Concerns\TargetsChannel;
use Illuminate\Foundation\Http\FormRequest;

class InitUploadRequest extends FormRequest
{
    use TargetsChannel;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels_id' => ['required', 'integer'],
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:255'],
        ];
    }
}
