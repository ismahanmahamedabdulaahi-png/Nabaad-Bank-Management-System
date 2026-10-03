<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LoanApplicationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_id'          => ['required', 'exists:customers,id'],
            'account_id'           => ['required', 'exists:accounts,id'],
            'amount'               => ['required', 'numeric', 'min:100', 'max:9999999.99'],
            'interest_rate'        => ['required', 'numeric', 'min:0', 'max:100'],
            'tenure_months'        => ['required', 'integer', 'min:1', 'max:360'],
            'first_repayment_date' => ['required', 'date', 'after:today'],
            'purpose'              => ['nullable', 'string', 'max:255'],
            'collateral'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
