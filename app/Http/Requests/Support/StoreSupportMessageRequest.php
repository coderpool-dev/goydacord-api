<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'image', 'max:12288'],
            'file' => ['nullable', 'file', 'image', 'max:12288'],
        ];
    }

    public function body(): ?string
    {
        return $this->validated('body');
    }

    /**
     * Скриншоты к обращению. Старые клиенты присылают один файл в поле file,
     * новые — массив files.
     *
     * @return UploadedFile[]
     */
    public function uploadedFiles(): array
    {
        $files = $this->file('files') ?? [];
        $files = is_array($files) ? array_values($files) : [$files];
        $single = $this->file('file');

        return $single instanceof UploadedFile ? [...$files, $single] : $files;
    }
}
