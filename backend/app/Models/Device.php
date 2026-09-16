<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'last_seen_at',
    ];

    protected $casts = [
        'ventilation'  => 'boolean',
        'online'       => 'boolean',
        'last_seen_at' => 'immutable_datetime',
    ];

    public function commandResults(): HasMany
    {
        return $this->hasMany(CommandResult::class, 'device_id', 'device_id');
    }
}
