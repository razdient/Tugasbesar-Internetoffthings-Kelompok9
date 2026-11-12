<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostureEvent extends Model
{
    protected $fillable = [
        'device_id',
        'angle',
        'direction',
        'duration',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'angle' => 'float',
            'started_at' => 'datetime',
        ];
    }

    // Kejadian postur berasal dari satu perangkat
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
