<?php

namespace App\Http\Requests\Servers;

use App\Http\Requests\Concerns\TargetsServerChannel;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerAttachmentRequest extends FormRequest
{
    use TargetsServerChannel;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_channel_id' => 'required|integer',
            'caption' => 'nullable|string|max:1000',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
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
