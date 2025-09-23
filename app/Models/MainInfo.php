<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MainInfo extends Model
{
    protected $fillable = [
        'invitation_id',
        'music_id',
        'main_photo',
        'wedding_date',
        'wedding_time',
        'time_zone',
        'custom_music',
    ];

    protected $casts = [
        'wedding_date' => 'date',
        'wedding_time' => 'datetime:H:i',
    ];

    protected $appends = [
        'main_photo_url',
        'custom_music_url',
    ];

    public function getMainPhotoUrlAttribute(): ?string
    {
        return $this->main_photo ? asset('storage/' . $this->main_photo) : null;
    }

    public function getCustomMusicUrlAttribute(): ?string
    {
        return $this->custom_music ? asset('storage/' . $this->custom_music) : null;
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }
}
