<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $primaryKey = 'device_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'device_id',
        'room_id',
        'ventilation',
        'boot_id',
        'online',
        'co2_alert',
        'last_seen_at',
    ];

    protected $casts = [
        'ventilation' => 'boolean',
        'online'      => 'boolean',
        'co2_alert'   => 'boolean',
        'last_seen_at' => 'immutable_datetime',
    ];

    public function latestTelemetry(): HasOne
    {
        return $this->hasOne(TelemetryOneMinute::class, 'device_id', 'device_id')
            ->ofMany(
                ['bucket' => 'MAX'],
                fn ($q) => $q->where('bucket', '<', now()->startOfMinute()),
            );
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(Telemetry::class, 'device_id', 'device_id');
    }

    public function commandResults(): HasMany
    {
        return $this->hasMany(CommandResult::class, 'device_id', 'device_id');
    }
}
