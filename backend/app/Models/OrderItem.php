<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        // Calculate subtotal before saving
        static::saving(function ($item) {
            $item->subtotal = $item->price * $item->quantity;
        });

        // Recalculate order total after item changes
        static::saved(function ($item) {
            $item->order->calculateTotal();
        });

        static::deleted(function ($item) {
            $item->order->calculateTotal();
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
