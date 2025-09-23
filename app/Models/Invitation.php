<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'theme_id',
        'status',
        'expiry_date',
        'groom',
        'bride',
        'slug'
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

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

    /**
     * Generate unique slug berdasarkan nama groom dan bride
     */
    public function generateUniqueSlug()
    {
        if (empty($this->groom) && empty($this->bride)) {
            $baseSlug = 'invitation-' . time();
        } elseif (empty($this->groom)) {
            $baseSlug = $this->createSlug($this->bride);
        } elseif (empty($this->bride)) {
            $baseSlug = $this->createSlug($this->groom);
        } else {
            $baseSlug = $this->createSlug($this->groom . ' & ' . $this->bride);
        }
        
        $slug = $baseSlug;
        $counter = 1;
        
        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Convert string menjadi slug format
     */
    private function createSlug($string)
    {
        $slug = strtolower(trim($string));
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug;
    }
}
