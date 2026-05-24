<?php
// app/Http/Requests/Master/Role/RoleRequest.php

namespace App\Http\Requests\Master\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->method();

        if ($method == 'POST') {
            return [
                'name' => 'required|string|max:100|unique:role,name|regex:/^[a-z_]+$/',
                'display_name' => 'required|string|max:150',
                'description' => 'nullable|string',
                'permission_ids' => 'nullable|array',
                'permission_ids.*' => 'exists:permissions,id',
            ];
        }

        if ($method == 'PUT' || $method == 'PATCH') {
            $id = $this->route('id');
            return [
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z_]+$/',
                    Rule::unique('role', 'name')->ignore($id)
                ],
                'display_name' => 'required|string|max:150',
                'description' => 'nullable|string',
                'permission_ids' => 'nullable|array',
                'permission_ids.*' => 'exists:permissions,id',
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Role name must only contain lowercase letters and underscores',
            'name.unique' => 'Role name already exists',
            'name.required' => 'Role name is required',
            'display_name.required' => 'Display name is required',
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
