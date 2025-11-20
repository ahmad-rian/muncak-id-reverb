<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Stream extends Model
{
    protected $fillable = [
        'user_id',
        'mountain_id',
        'jalur_id',
        'title',
        'description',
        'stream_key',
        'location',
        'thumbnail_url',
        'status',
        'quality',
        'viewer_count',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mountain(): BelongsTo
    {
        return $this->belongsTo(Gunung::class, 'mountain_id');
    }

    public function jalur(): BelongsTo
    {
        return $this->belongsTo(Rute::class, 'jalur_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(StreamSession::class);
    }

    public function latestClassification(): HasOne
    {
        return $this->hasOne(TrailClassification::class)->latestOfMany('classified_at');
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }
}
