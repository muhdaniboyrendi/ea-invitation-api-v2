<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Music extends Model
{
    protected $fillable = [
        'name',
        'artist',
        'audio',
        'thumbnail',
    ];

    protected $appends = [
        'audio_url', 
        'thumbnail_url'
    ];

    public function getAudioUrlAttribute(): ?string
    {
        return $this->audio ? asset('storage/' . $this->audio) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
    }

    public function mainInfos(): HasMany
    {
        return $this->hasMany(MainInfo::class);
    }
}
