<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    protected $fillable = [
        'invitation_id',
        'videos',
    ];

    protected $appends = [
        'video_urls',
    ];

    protected $casts = [
        'videos' => 'array',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get the full URLs of all videos
     */
    public function getVideoUrlsAttribute(): array
    {
        if (!$this->videos || !is_array($this->videos)) {
            return [];
        }
        
        return array_map(function($video) {
            return asset('storage/' . $video);
        }, $this->videos);
    }
}
