<?php

namespace App\Http\Requests\Uploads;

use Illuminate\Foundation\Http\FormRequest;

/** Кусок файла приходит сырым телом, смещение — в заголовке Upload-Offset. */
class UploadChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['offset' => $this->header('Upload-Offset')]);
    }

    public function rules(): array
    {
        return [
            'offset' => ['required', 'integer', 'min:0'],
        ];
    }

    public function offset(): int
    {
        return (int) $this->validated('offset');
    }

    public function chunk(): string
    {
        return $this->getContent();
    }
}
