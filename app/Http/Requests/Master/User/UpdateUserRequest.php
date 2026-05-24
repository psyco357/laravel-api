<?php

namespace App\Http\Requests\Master\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('mst_user', 'username')->ignore($userId)
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('mst_user', 'email')->ignore($userId)
            ],
            'password' => ['nullable', 'string', 'min:8', Password::defaults()],
            'password_confirmation' => 'required_with:password|same:password',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',

            // Profile fields
            'full_name' => 'required|string|max:150',
            'nik' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('mst_profiles', 'nik')->ignore($userId, 'user_id')
            ],
            'phone' => 'nullable|string|max:30',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'remove_avatar' => 'boolean',

            // Assignment fields
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:role,id',
            'app_ids' => 'nullable|array',
            'app_ids.*' => 'exists:mst_app,id',
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
