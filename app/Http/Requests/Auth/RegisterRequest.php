<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'app_id' => $this->input('app_id', $this->header('X-App-Id')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_id' => [
                'nullable',
                'integer',
                Rule::exists('central.mst_app', 'id')->where(function ($query) {
                    $query->where('is_active', true)->whereNull('deleted_at');
                }),
            ],
            'username' => ['required', 'string', 'max:100', 'unique:central.mst_user,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:central.mst_user,email'],
            'full_name' => ['required', 'string', 'max:150'],
            'nik' => ['nullable', 'string', 'max:50', 'unique:central.mst_profiles,nik'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
