<?php
// app/Http/Requests/Master/Permission/UpdatePermissionRequest.php

namespace App\Http\Requests\Master\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit_permission');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z_]+$/',
                Rule::unique('permissions', 'name')->ignore($this->permission)
            ],
            'display_name' => 'required|string|max:150',
            'group' => 'nullable|string|max:100',
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
            'required_permissions' => ['edit_permission'],
        ], 403));
    }
}
