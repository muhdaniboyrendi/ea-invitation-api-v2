<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'theme_category_id',
        'link',
        'thumbnail',
        'is_premium'
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function themeCategory(): BelongsTo
    {
        return $this->belongsTo(ThemeCategory::class);
    }

    public function getThumbnailUrlAttribute()
    {
        if ($this->thumbnail) {
            return asset('storage/' . $this->thumbnail);
        }
        
        return null;
    }
}
