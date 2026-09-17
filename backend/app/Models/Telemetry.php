<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'co2' => 'integer',
    ];

    public function scopeBucketed(Builder $query, int $minutes): Builder
    {
        return $query
            ->selectRaw(
                "time_bucket(? * interval '1 minute', observed_at) AS observed_at,
                 (ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY temperature)::numeric * 2) / 2.0)::float AS temperature,
                 ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY co2))::integer AS co2",
                [$minutes]
            )
            ->groupByRaw('1');
    }
}
