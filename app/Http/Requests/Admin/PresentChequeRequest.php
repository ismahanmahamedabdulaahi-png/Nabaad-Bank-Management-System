<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PresentChequeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ];
    }
}
