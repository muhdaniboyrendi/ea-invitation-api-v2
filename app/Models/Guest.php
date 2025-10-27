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

        static::updating(function ($guest) {
            // Cek apakah name berubah
            if ($guest->isDirty('name')) {
                // Generate slug baru berdasarkan name yang baru
                $guest->slug = static::generateUniqueSlug($guest->name, $guest->id);
            }
        });
    }

    public static function generateUniqueSlug($name, $ignoreId = null)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)
            ->when($ignoreId, function ($query) use ($ignoreId) {
                return $query->where('id', '!=', $ignoreId);
            })
            ->exists()) {
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