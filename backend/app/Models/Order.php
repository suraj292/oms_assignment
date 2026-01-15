<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'status',
        'total',
        'notes',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        // Generate order number on creation
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = 'ORD-' . strtoupper(uniqid());
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Calculate and update order total from items
     */
    public function calculateTotal(): void
    {
        $this->total = $this->items()->sum('subtotal');
        $this->save();
    }

    /**
     * Check if order can be edited
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if order is in final state
     */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Transition to new status with validation
     */
    public function transitionTo(OrderStatus $newStatus): bool
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            return false;
        }

        $this->status = $newStatus;
        return $this->save();
    }

    /**
     * Get allowed next statuses
     */
    public function allowedNextStatuses(): array
    {
        return $this->status->allowedTransitions();
    }

    public function scopeByStatus($query, $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhereHas('customer', function ($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
              });
        });
    }
}
