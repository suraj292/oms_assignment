<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Searchable;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'status',
        'image',
        'document',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'description'];
    }
}
