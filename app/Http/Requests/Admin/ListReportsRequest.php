<?php

namespace App\Http\Requests\Admin;

use App\Models\Support\UserReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                UserReport::STATUS_NEW,
                UserReport::STATUS_REVIEWED,
                UserReport::STATUS_DISMISSED,
            ])],
            'q' => ['nullable', 'string', 'max:190'],
        ];
    }
}
