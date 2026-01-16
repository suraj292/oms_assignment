<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitializeUploadRequest extends FormRequest
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
            'filename' => ['required', 'string', 'max:255'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'],
            'file_size' => ['required', 'integer', 'min:1', 'max:5368709120'], // 5GB max
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'filename.required' => 'Filename is required',
            'total_chunks.required' => 'Total chunks count is required',
            'total_chunks.max' => 'File has too many chunks (max 10000)',
            'file_size.required' => 'File size is required',
            'file_size.max' => 'File size exceeds maximum allowed (5GB)',
        ];
    }
}
