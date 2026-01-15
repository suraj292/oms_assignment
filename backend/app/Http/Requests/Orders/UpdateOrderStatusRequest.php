<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
        $order = $this->route('order');
        $allowedStatuses = array_map(
            fn($status) => $status->value,
            $order->allowedNextStatuses()
        );

        return [
            'status' => [
                'required',
                Rule::in($allowedStatuses)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status transition. This status change is not allowed.',
        ];
    }
}
