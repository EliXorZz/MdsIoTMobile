<?php

namespace App\Models;

use App\Enums\TelemetryResolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TelemetryAggregate extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $casts = [
        'bucket' => 'immutable_datetime',
        'median_temperature' => 'float',
        'min_temperature' => 'float',
        'max_temperature' => 'float',
        'median_co2' => 'float',
        'min_co2' => 'float',
        'max_co2' => 'float',
        'samples' => 'integer',
    ];

    public static function resolution(TelemetryResolution $resolution): Builder
    {
        return (new static)
            ->setTable($resolution->view())
            ->newQuery();
    }
}
