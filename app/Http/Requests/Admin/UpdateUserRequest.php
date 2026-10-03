<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.edit');
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', "unique:users,email,{$userId}"],
            'phone'             => ['nullable', 'string', 'max:20'],
            'password'          => ['nullable', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'role'              => ['required', 'string', 'exists:roles,name'],
            'transaction_limit' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'status'            => ['required', 'in:active,inactive,suspended'],
        ];
    }
}
