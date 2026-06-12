<?php

namespace App\Http\Requests\Master\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'apps' => 'required|array|min:1',
            'apps.*.app_id' => 'required|exists:mst_app,id',
            'apps.*.role_id' => 'nullable|exists:role,id',
            'apps.*.is_active' => 'boolean',
            'sync_type' => 'nullable|in:sync,attach,detach',
        ];
    }

    public function messages(): array
    {
        return [
            'apps.required' => 'At least one app is required',
            'apps.*.app_id.required' => 'App ID is required',
            'apps.*.app_id.exists' => 'Invalid app ID',
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
