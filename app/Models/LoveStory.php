<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoveStory extends Model
{
    protected $fillable = [
        'invitation_id',
        'title',
        'date',
        'description',
        'thumbnail',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get the full URL for the thumbnail.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
    }
}
