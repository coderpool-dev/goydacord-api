<?php

namespace App\Http\Requests\LinkPreview;

use Illuminate\Foundation\Http\FormRequest;

class ShowLinkPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
        ];
    }
}
