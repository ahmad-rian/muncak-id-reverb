<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrailClassification extends Model
{
    protected $fillable = [
        'stream_id',
        'trail_name',
        'classified_at',
        'weather',
        'crowd_density',
        'visibility',
        'confidence_weather',
        'confidence_crowd',
        'confidence_visibility',
        'image_path',
    ];

    protected $casts = [
        'classified_at' => 'datetime',
        'confidence_weather' => 'float',
        'confidence_crowd' => 'float',
        'confidence_visibility' => 'float',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * Get weather label in Indonesian
     */
    public function getWeatherLabelAttribute(): string
    {
        return match ($this->weather) {
            'cerah' => 'Cerah',
            'berawan' => 'Berawan/Berkabut',
            'hujan' => 'Hujan',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Get crowd density label in Indonesian
     */
    public function getCrowdLabelAttribute(): string
    {
        return match ($this->crowd_density) {
            'sepi' => 'Sepi (0-2 pendaki)',
            'sedang' => 'Sedang (3-10 pendaki)',
            'ramai' => 'Ramai (>10 pendaki)',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Get visibility label in Indonesian
     */
    public function getVisibilityLabelAttribute(): string
    {
        return match ($this->visibility) {
            'jelas' => 'Jelas',
            'kabut_sedang' => 'Kabut Sedang',
            'kabut_tebal' => 'Tertutup Kabut',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Get weather icon
     */
    public function getWeatherIconAttribute(): string
    {
        return match ($this->weather) {
            'cerah' => '☀️',
            'berawan' => '⛅',
            'hujan' => '🌧️',
            default => '❓',
        };
    }

    /**
     * Get recommendation based on conditions
     */
    public function getRecommendationAttribute(): string
    {
        if ($this->weather === 'hujan') {
            return 'Tidak disarankan mendaki saat hujan!';
        }

        if ($this->visibility === 'kabut_tebal') {
            return 'Perhatikan kondisi kabut tebal!';
        }

        if ($this->crowd_density === 'ramai') {
            return 'Jalur sedang ramai, pertimbangkan waktu pendakian.';
        }

        if ($this->weather === 'cerah' && $this->visibility === 'jelas') {
            return 'Kondisi bagus untuk mendaki!';
        }

        if ($this->visibility === 'kabut_sedang') {
            return 'Perhatikan kondisi kabut!';
        }

        return 'Kondisi normal, tetap waspada.';
    }

    /**
     * Get recommendation icon
     */
    public function getRecommendationIconAttribute(): string
    {
        if ($this->weather === 'hujan' || $this->visibility === 'kabut_tebal') {
            return '⚠️';
        }

        if ($this->weather === 'cerah' && $this->visibility === 'jelas' && $this->crowd_density !== 'ramai') {
            return '💡';
        }

        return '📝';
    }

    /**
     * Get classified_at in WIB timezone
     */
    public function getClassifiedAtWibAttribute(): string
    {
        return $this->classified_at
            ->setTimezone('Asia/Jakarta')
            ->format('d M Y, H:i:s') . ' WIB';
    }
}
