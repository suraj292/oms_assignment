<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total' => $this->total,
            'notes' => $this->notes,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items->count() ?? 0,
            'documents' => $this->when($this->relationLoaded('documents'), function() {
                return $this->getRelation('documents');
            }),
            'is_editable' => $this->isEditable(),
            'is_final' => $this->isFinal(),
            'allowed_next_statuses' => array_map(
                fn($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                $this->allowedNextStatuses()
            ),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
