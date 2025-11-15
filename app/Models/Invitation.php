<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'theme_id',
        'status',
        'expiry_date',
        'groom_name',
        'bride_name',
        'slug'
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    /**
     * Boot method untuk auto-generate dan update slug
     */
    protected static function boot()
    {
        parent::boot();

        // Generate slug saat creating (insert baru)
        static::creating(function ($invitation) {
            if (empty($invitation->slug)) {
                $invitation->slug = $invitation->generateUniqueSlug();
            }
        });

        // Update slug saat updating (jika groom_name atau bride_name berubah)
        static::updating(function ($invitation) {
            // Cek apakah groom_name atau bride_name berubah
            if ($invitation->isDirty('groom_name') || $invitation->isDirty('bride_name')) {
                $invitation->slug = $invitation->generateUniqueSlug();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function mainInfo(): HasOne
    {
        return $this->hasOne(MainInfo::class);
    }

    public function groom(): HasOne
    {
        return $this->hasOne(Groom::class);
    }

    public function bride(): HasOne
    {
        return $this->hasOne(Bride::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function loveStories(): HasMany
    {
        return $this->hasMany(LoveStory::class);
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * Generate unique slug berdasarkan nama groom dan bride
     */
    public function generateUniqueSlug()
    {
        if (empty($this->groom_name) && empty($this->bride_name)) {
            $baseSlug = 'invitation-' . time();
        } elseif (empty($this->groom_name)) {
            $baseSlug = Str::slug($this->bride_name);
        } elseif (empty($this->bride_name)) {
            $baseSlug = Str::slug($this->groom_name);
        } else {
            $baseSlug = Str::slug($this->groom_name . '-' . $this->bride_name);
        }
        
        $slug = $baseSlug;
        $counter = 1;
        
        // Cek apakah slug sudah ada (exclude ID saat ini untuk update)
        $query = static::where('slug', $slug);
        
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }
        
        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            
            $query = static::where('slug', $slug);
            if ($this->exists) {
                $query->where('id', '!=', $this->id);
            }
        }
        
        return $slug;
    }

    /**
     * Convert string menjadi slug format (deprecated - gunakan Str::slug)
     * Kept for backward compatibility
     */
    private function createSlug($string)
    {
        return Str::slug($string);
    }
}