<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
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
            'phone'         => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'email'         => ['required', 'email', 'max:255', 'unique:customers,email'],
            'address'       => ['nullable', 'string', 'max:1000'],
            'city'          => ['nullable', 'string', 'max:100'],

            // Next of Kin
            'next_of_kin_name'         => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pM\s\'\-\.]+$/u'],
            'next_of_kin_phone'        => ['nullable', 'string', 'max:20'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:100'],

            // Files
            'photo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique'              => 'This phone number is already registered.',
            'email.unique'              => 'This email address is already registered.',
            'date_of_birth.before_or_equal' => 'Customer must be at least 18 years old.',
            'name.regex'                => 'Name may only contain letters, spaces, apostrophes, hyphens, and periods — no numbers.',
            'next_of_kin_name.regex'    => 'Name may only contain letters, spaces, apostrophes, hyphens, and periods — no numbers.',
        ];
    }
}
