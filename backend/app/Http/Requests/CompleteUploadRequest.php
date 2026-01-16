<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', 'in:order_document,product_document'],
            'target_id' => ['required', 'integer', 'exists:' . $this->getTargetTable() . ',id'],
        ];
    }

    /**
     * Get the target table based on target_type
     */
    private function getTargetTable(): string
    {
        return match($this->input('target_type')) {
            'order_document' => 'orders',
            'product_document' => 'products',
            default => 'orders',
        };
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'target_type.required' => 'Target type is required',
            'target_type.in' => 'Invalid target type',
            'target_id.required' => 'Target ID is required',
            'target_id.exists' => 'Target record not found',
        ];
    }
}
