<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateStandingOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'source_account_id'      => ['required', 'exists:accounts,id'],
            'beneficiary_account_id' => ['required', 'exists:accounts,id', 'different:source_account_id'],
            'amount'                 => ['required', 'numeric', 'min:0.01'],
            'frequency'              => ['required', 'in:weekly,monthly'],
            'start_date'             => ['required', 'date', 'after_or_equal:today'],
            'end_date'               => ['nullable', 'date', 'after:start_date'],
            'description'            => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_account_id.different' => 'Source and beneficiary accounts must be different.',
            'start_date.after_or_equal'        => 'Start date must be today or a future date.',
        ];
    }
}
