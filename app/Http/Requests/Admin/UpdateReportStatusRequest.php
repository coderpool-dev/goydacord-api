<?php

namespace App\Http\Requests\Admin;

use App\Models\Support\UserReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                UserReport::STATUS_NEW,
                UserReport::STATUS_REVIEWED,
                UserReport::STATUS_DISMISSED,
            ])],
        ];
    }
}
