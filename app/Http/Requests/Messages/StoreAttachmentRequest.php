<?php

namespace App\Http\Requests\Messages;

use App\Http\Requests\Concerns\TargetsChannel;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    use TargetsChannel;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels_id' => 'required|integer',
            'caption' => 'nullable|string|max:1000',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
            // 25 МБ. Вайтлист расширений — без исполняемых/svg/html (защита от XSS и запуска).
            'file' => 'required|file|max:25600|mimes:jpeg,jpg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,mp3,mp4,mov,m4a,aac,wav,ogg,oga,webm,flac',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Файл обязателен',
            'file.max' => 'Файл не должен превышать 25 МБ',
            'file.mimes' => 'Недопустимый тип файла',
        ];
    }
}
