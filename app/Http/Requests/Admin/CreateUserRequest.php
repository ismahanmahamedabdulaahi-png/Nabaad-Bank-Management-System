<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    // No password/status here — new staff are always created in the `invited`
    // state and set their own password via the invitation flow (see
    // UserService::create and StaffInvitationController).
    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'role'              => ['required', 'string', 'exists:roles,name'],
            'transaction_limit' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_limit.*' => 'Enter a valid transaction limit.',
        ];
    }
}
