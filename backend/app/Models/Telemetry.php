<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Telemetry extends Model
{
    protected $table = 'telemetry';

    protected $primaryKey = 'message_id';
    protected $keyType = 'string';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'observed_at',
        'device_id',
        'room_id',
        'message_id',
        'temperature',
        'co2',
    ];

    protected $casts = [
        'observed_at' => 'immutable_datetime',
        'temperature' => 'float',
        'co2'         => 'integer',
    ];
}
