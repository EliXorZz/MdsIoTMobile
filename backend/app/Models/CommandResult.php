<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandResult extends Model
{
    protected $fillable = [
        'device_id',
        'command_id',
        'status',
        'ventilation',
        'reason',
        'executed_at',
        'reported_at',
    ];

    protected $casts = [
        'ventilation' => 'boolean',
        'executed_at' => 'immutable_datetime',
        'reported_at' => 'immutable_datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }
}
