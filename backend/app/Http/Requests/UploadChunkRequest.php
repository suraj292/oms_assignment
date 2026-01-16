<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadChunkRequest extends FormRequest
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
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:2048'], // 2MB max per chunk
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
            'chunk_index.required' => 'Chunk index is required',
            'chunk_index.integer' => 'Chunk index must be an integer',
            'chunk.required' => 'Chunk file is required',
            'chunk.file' => 'Chunk must be a file',
            'chunk.max' => 'Chunk size exceeds maximum allowed (2MB)',
        ];
    }
}
