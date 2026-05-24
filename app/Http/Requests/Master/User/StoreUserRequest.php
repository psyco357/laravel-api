<?php

namespace App\Http\Requests\Master\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:100|unique:mst_user,username',
            'email' => 'required|email|max:255|unique:mst_user,email',
            'password' => ['required', 'string', 'min:8', Password::defaults()],
            'password_confirmation' => 'required|same:password',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',

            // Profile fields
            'full_name' => 'required|string|max:150',
            'nik' => 'nullable|string|max:50|unique:mst_profiles,nik',
            'phone' => 'nullable|string|max:30',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // Assignment fields
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:role,id',
            'app_ids' => 'nullable|array',
            'app_ids.*' => 'exists:mst_app,id',
            'default_app_id' => 'nullable|exists:mst_app,id',
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username is required',
            'username.unique' => 'Username already exists',
            'email.required' => 'Email is required',
            'email.unique' => 'Email already exists',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password_confirmation.same' => 'Password confirmation does not match',
            'full_name.required' => 'Full name is required',
            'nik.unique' => 'NIK already exists',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422));
    }
}
