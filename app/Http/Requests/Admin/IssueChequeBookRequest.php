<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IssueChequeBookRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_id'  => ['required', 'exists:customers,id'],
            'account_id'   => ['required', 'exists:accounts,id'],
            'total_leaves' => ['nullable', 'integer', 'min:5', 'max:200'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
