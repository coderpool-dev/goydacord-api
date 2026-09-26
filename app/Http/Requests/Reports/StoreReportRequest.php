<?php

namespace App\Http\Requests\Reports;

use App\Models\Support\UserReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(UserReport::REASONS)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
