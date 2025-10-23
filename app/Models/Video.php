<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    protected $fillable = [
        'invitation_id',
        'video',
    ];

    protected $appends = [
        'video_url',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get the full URLs of all videos
     */
    public function getVideoUrlAttribute(): ?string
    {
        return $this->video ? asset('storage/' . $this->video) : null;
    }
}
