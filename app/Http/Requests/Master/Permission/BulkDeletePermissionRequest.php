<?php
// app/Http/Requests/Master/Permission/BulkDeletePermissionRequest.php

namespace App\Http\Requests\Master\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkDeletePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete_permission');
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:permissions,id'
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'At least one permission ID is required',
            'ids.*.exists' => 'One or more permission IDs are invalid',
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
            'required_permissions' => ['delete_permission'],
        ], 403));
    }
}
