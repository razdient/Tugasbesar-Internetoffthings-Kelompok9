<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorReading extends Model
{
    protected $fillable = [
        'device_id',
        'pitch',
        'roll',
        'status',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'pitch' => 'float',
            'roll' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    // Pembacaan sensor berasal dari satu perangkat
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
