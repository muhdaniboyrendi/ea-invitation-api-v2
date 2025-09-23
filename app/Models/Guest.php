<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    protected $fillable = [
        'invitation_id',
        'name',
        'slug',
        'phone',
        'is_group',
        'attendance_status',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($guest) {
            if (empty($guest->slug)) {
                $guest->slug = static::generateUniqueSlug($guest->name);
            }
        });
    }

    public static function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
