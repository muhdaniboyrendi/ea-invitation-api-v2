<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'theme_category_id',
        'slug',
        'thumbnail',
        'is_premium'
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    /**
     * Boot method untuk auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        // Generate slug saat creating (insert baru)
        static::creating(function ($theme) {
            if (empty($theme->slug)) {
                $theme->slug = static::generateUniqueSlug($theme->name);
            }
        });

        // Update slug saat updating (jika nama berubah)
        static::updating(function ($theme) {
            if ($theme->isDirty('name')) {
                $theme->slug = static::generateUniqueSlug($theme->name, $theme->id);
            }
        });
    }

    /**
     * Generate slug yang unik
     */
    public static function generateUniqueSlug($name, $ignoreId = null)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        // Cek apakah slug sudah ada
        while (static::slugExists($slug, $ignoreId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Cek apakah slug sudah ada di database
     */
    protected static function slugExists($slug, $ignoreId = null)
    {
        $query = static::where('slug', $slug);
        
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        
        return $query->exists();
    }

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