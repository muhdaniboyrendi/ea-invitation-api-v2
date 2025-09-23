<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThemeCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class);
    }
}
