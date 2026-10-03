<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TillCashMovementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'to_till_id'   => ['nullable', 'exists:teller_tills,id'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
