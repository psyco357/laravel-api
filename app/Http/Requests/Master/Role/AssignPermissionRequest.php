<?php
// app/Http/Requests/Master/Role/AssignPermissionRequest.php

namespace App\Http\Requests\Master\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => 'required|array|min:1',
            'permission_ids.*' => 'exists:permissions,id',
            'sync_type' => 'nullable|in:sync,attach,detach',
        ];
    }

    public function messages(): array
    {
        return [
            'permission_ids.required' => 'At least one permission is required',
            'permission_ids.*.exists' => 'One or more permission IDs are invalid',
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
