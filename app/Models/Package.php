<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'price',
        'discount',
        'features',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'integer',
        'features' => 'array',
    ];

    protected $appends = [
        'final_price',
    ];

    public function getFinalPriceAttribute()
    {
        if ($this->discount) {
            return $this->price - ($this->price * $this->discount / 100);
        }
        
        return $this->price;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
