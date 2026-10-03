<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('customer')->id;

        return [
            // Personal
            'name'          => ['required', 'string', 'max:255', 'regex:/^[\pL\pM\s\'\-\.]+$/u'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'gender'        => ['nullable', 'in:male,female,other'],
            'nationality'   => ['nullable', 'string', 'max:100'],
            'region'        => ['nullable', 'string', 'max:100'],
            'occupation'    => ['nullable', 'string', 'max:255'],
            'marital_status'=> ['nullable', 'in:single,married,divorced,widowed'],

            // Contact
            'phone'         => ['required', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($id)],
            'email'         => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($id)],
            'address'       => ['nullable', 'string', 'max:1000'],
            'city'          => ['nullable', 'string', 'max:100'],

            // Next of Kin
            'next_of_kin_name'         => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pM\s\'\-\.]+$/u'],
            'next_of_kin_phone'        => ['nullable', 'string', 'max:20'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:100'],

            // Status (admin override)
            'status'        => ['required', 'in:pending,active,inactive,blacklisted,deceased'],

            // Files
            'photo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'             => 'Name may only contain letters, spaces, apostrophes, hyphens, and periods — no numbers.',
            'next_of_kin_name.regex' => 'Name may only contain letters, spaces, apostrophes, hyphens, and periods — no numbers.',
        ];
    }
}
