<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use Notifiable, Searchable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'email', 'phone'];
    }
}

