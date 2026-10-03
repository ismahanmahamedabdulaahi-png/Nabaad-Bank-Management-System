<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $isFixed = $this->input('account_type') === 'fixed_deposit';

        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('status', 'active'),
            ],
            'branch_id'        => ['required', 'exists:branches,id'],
            'account_type'     => ['required', 'in:savings,current,fixed_deposit'],
            'currency'         => ['nullable', 'string', 'size:3'],
            'opening_balance'  => ['nullable', 'numeric', 'min:0'],
            'interest_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_balance'  => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:1000'],

            // Fixed Deposit only
            'fd_tenure_months'   => [$isFixed ? 'required' : 'nullable', 'integer', 'min:1', 'max:360'],
            'fd_maturity_action' => [$isFixed ? 'required' : 'nullable', 'in:renew,transfer_to_savings,pending'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required'    => 'Please select a customer.',
            'customer_id.exists'      => 'Selected customer does not exist or is not active.',
            'branch_id.required'      => 'Please select a branch.',
            'fd_tenure_months.required' => 'Tenure (months) is required for Fixed Deposit accounts.',
            'fd_maturity_action.required' => 'Please select what happens at maturity.',
        ];
    }
}
