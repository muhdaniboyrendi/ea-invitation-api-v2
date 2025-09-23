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
        'time_end',
        'address',
        'maps_url',
        'maps_embed_url',
    ];

    protected $casts = [
        'date' => 'date',
        'time_start' => 'datetime:H:i',
        'time_end' => 'datetime:H:i',
    ];

    protected $appends = [
        'formatted_date',
        'formatted_time_range',
        'formatted_date_time',
        'is_past_event',
        'days_until_event',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Check if event has location details.
     */
    public function hasLocationDetails(): bool
    {
        return !empty($this->address) || !empty($this->maps_url) || !empty($this->maps_embed_url);
    }

    /**
     * Convert regular Google Maps URL to embed URL.
     */
    public static function convertToEmbedUrl(string $mapsUrl): ?string
    {
        if (empty($mapsUrl)) {
            return null;
        }

        // If it's already an embed URL, return it
        if (strpos($mapsUrl, 'embed') !== false) {
            return $mapsUrl;
        }

        // Parse different Google Maps URL formats
        $patterns = [
            // Format: https://www.google.com/maps/place/.../@lat,lng,zoom
            '/maps\/place\/[^@]*@(-?\d+\.\d+),(-?\d+\.\d+),(\d+)/' => function($matches) {
                return "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d{$matches[3]}!2d{$matches[2]}!3d{$matches[1]}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zM{$matches[1]},{$matches[2]}!5e0!3m2!1sen!2sid!4v1";
            },
            
            // Format: https://goo.gl/maps/... or https://maps.app.goo.gl/...
            '/(?:goo\.gl\/maps|maps\.app\.goo\.gl)\/([a-zA-Z0-9]+)/' => function($matches) {
                return "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966!2d106.8!3d-6.2!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s{$matches[1]}!2s!5e0!3m2!1sen!2sid!4v1";
            },
            
            // Format: https://www.google.com/maps?q=lat,lng
            '/maps\?q=(-?\d+\.\d+),(-?\d+\.\d+)/' => function($matches) {
                return "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966!2d{$matches[2]}!3d{$matches[1]}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2z{$matches[1]},{$matches[2]}!5e0!3m2!1sen!2sid!4v1";
            },
            
            // Format: https://www.google.com/maps/search/place+name
            '/maps\/search\/([^\/\?&]+)/' => function($matches) {
                $query = urlencode($matches[1]);
                return "https://www.google.com/maps/embed/v1/search?key=YOUR_API_KEY&q={$query}";
            },
            
            // Format: https://maps.google.com/?q=place+name
            '/maps\.google\.com\/\?q=([^&]+)/' => function($matches) {
                $query = urlencode($matches[1]);
                return "https://www.google.com/maps/embed/v1/search?key=YOUR_API_KEY&q={$query}";
            }
        ];

        foreach ($patterns as $pattern => $callback) {
            if (preg_match($pattern, $mapsUrl, $matches)) {
                return $callback($matches);
            }
        }

        // If no pattern matches, try to extract coordinates manually
        if (preg_match('/(-?\d+\.\d+),(-?\d+\.\d+)/', $mapsUrl, $matches)) {
            return "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966!2d{$matches[2]}!3d{$matches[1]}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2z{$matches[1]},{$matches[2]}!5e0!3m2!1sen!2sid!4v1";
        }

        return null;
    }

    /**
     * Get Google Calendar URL for the event.
     */
    public function getGoogleCalendarUrl(): string
    {
        $startDateTime = $this->date->format('Ymd') . 'T' . $this->time_start->format('His');
        $endDateTime = $this->time_end 
            ? $this->date->format('Ymd') . 'T' . $this->time_end->format('His')
            : $this->date->format('Ymd') . 'T' . $this->time_start->addHour()->format('His');

        $params = [
            'action' => 'TEMPLATE',
            'text' => urlencode($this->name),
            'dates' => $startDateTime . '/' . $endDateTime,
            'location' => urlencode($this->venue . ($this->address ? ', ' . $this->address : '')),
            'details' => urlencode('Event: ' . $this->name),
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    /**
     * Mutator for maps_url - automatically generate embed URL when regular URL is set.
     */
    public function setMapsUrlAttribute($value)
    {
        $this->attributes['maps_url'] = $value;
        
        // Auto-generate embed URL if maps_url is provided and embed_url is not manually set
        if (!empty($value) && empty($this->attributes['maps_embed_url'])) {
            $embedUrl = self::convertToEmbedUrl($value);
            if ($embedUrl) {
                $this->attributes['maps_embed_url'] = $embedUrl;
            }
        }
    }

    /**
     * Get the embed URL (with fallback to auto-conversion).
     */
    public function getEmbedUrlAttribute(): ?string
    {
        // Return existing embed URL if available
        if (!empty($this->maps_embed_url)) {
            return $this->maps_embed_url;
        }

        // Try to convert regular maps URL to embed URL
        if (!empty($this->maps_url)) {
            return self::convertToEmbedUrl($this->maps_url);
        }

        return null;
    }
}
