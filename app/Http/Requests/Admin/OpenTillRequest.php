<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OpenTillRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'teller_id'       => ['required', 'exists:users,id'],
            'till_name'       => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'business_date'   => ['nullable', 'date'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
