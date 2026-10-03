<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LoanRepaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'exists:loan_repayment_schedules,id'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
