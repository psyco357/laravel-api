<?php
// app/Http/Requests/Master/Permission/StorePermissionRequest.php

namespace App\Http\Requests\Master\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_permission');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:permissions,name|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:150',
            'group' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Permission name must only contain lowercase letters and underscores',
            'name.unique' => 'Permission name already exists',
            'name.required' => 'Permission name is required',
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

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Forbidden. You do not have the required permission.',
            'errors' => [
                'permission' => ['You do not have the required permission.'],
            ],
            'required_permissions' => ['create_permission'],
        ], 403));
    }
}
