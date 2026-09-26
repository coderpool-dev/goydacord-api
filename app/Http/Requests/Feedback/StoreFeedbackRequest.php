<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'body' => ['required', 'string', 'min:10', 'max:4000'],
            'consent' => ['accepted'],
            'page' => ['nullable', 'string', 'max:512'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Нужно согласие на обработку персональных данных',
            'website.max' => 'Обращение не отправлено',
        ];
    }
}
