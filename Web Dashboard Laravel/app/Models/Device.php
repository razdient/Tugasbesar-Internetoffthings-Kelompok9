<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'name',
        'last_seen_at',
        'angle_threshold',
        'duration_threshold',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'angle_threshold' => 'decimal:2',
        ];
    }

    // Perangkat dimiliki oleh satu pengguna
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Perangkat memiliki banyak pembacaan sensor
    public function sensorReadings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    // Perangkat memiliki banyak kejadian postur
    public function postureEvents(): HasMany
    {
        return $this->hasMany(PostureEvent::class);
    }
}
