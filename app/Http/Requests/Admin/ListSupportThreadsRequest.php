<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListSupportThreadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'in:inbox,spam'],
            'unread' => ['nullable', 'in:0,1,true,false'],
            'sort' => ['nullable', 'in:last_message_at,created_at,id'],
            'order' => ['nullable', 'in:asc,desc'],
        ];
    }
}
