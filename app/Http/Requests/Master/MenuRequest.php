<?php

namespace App\Http\Requests\Master;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    // protected function prepareForValidation(): void
    // {
    //     $this->merge([
    //         'app_id' => $this->input('app_id', $this->header('X-App-Id')),
    //     ]);
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|exists:menu,id',
            'type' => 'required|string|max:50',
            'name' => 'required|string|max:150',
            'icon' => 'nullable|string|max:100',
            'path' => 'nullable|string|max:255',
            'badge_key' => 'nullable|string|max:100',
            'step' => 'nullable|integer',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ];
    }
}
