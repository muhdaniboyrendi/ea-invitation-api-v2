<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = [
        'invitation_id',
        'name',
        'venue',
        'date',
        'time_start',
        'address',
        'maps_url',
        'maps_embed_url',
    ];

    protected $casts = [
        'date' => 'date',
        'time_start' => 'datetime:H:i',
    ];

    protected $appends = [
        // 
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
