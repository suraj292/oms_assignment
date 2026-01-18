<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use Searchable;
    
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

        static::creating(function ($order) {
            if (!$order->order_number) {
                // auto-generate order number if not provided
                $order->order_number = 'ORD-' . strtoupper(uniqid());
            }
        });

        // Dispatch job to send notifications in background
        // This ensures the order creation response is fast and notifications
        // are sent asynchronously via the queue worker
        static::created(function ($order) {
            \App\Jobs\SendOrderCreatedNotifications::dispatch(
                $order,
                auth()->id()
            );
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

    public function documents()
    {
        return $this->hasMany(OrderDocument::class);
    }

    public function calculateTotal(): void
    {
        $this->total = $this->items()->sum('subtotal');
        $this->save();
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    // handles status transitions with validation
    // returns false if the transition isn't allowed by the OrderStatus enum
    public function transitionTo(OrderStatus $newStatus): bool
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            return false;
        }

        $previousStatus = $this->status;
        $this->status = $newStatus;
        $wasSuccessful = $this->save();

        if ($wasSuccessful) {
            $this->load('customer');
            
            // notify customer about status change
            if ($this->customer) {
                $this->customer->notify(new OrderStatusChangedNotification($this, $previousStatus, $newStatus));
            }
            
            // notify the person who changed it too
            if (auth()->check()) {
                auth()->user()->notify(new OrderStatusChangedNotification($this, $previousStatus, $newStatus));
            }
        }

        return $wasSuccessful;
    }

    public function allowedNextStatuses(): array
    {
        return $this->status->allowedTransitions();
    }

    protected function getSearchableFields(): array
    {
        return ['order_number'];
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}

