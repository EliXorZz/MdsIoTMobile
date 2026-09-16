<?php

namespace App\Models;

class TelemetryFiveMinute extends TelemetryAggregate
{
    protected $table = 'telemetry_5m';

    protected $primaryKey = 'bucket';

    protected $keyType = 'datetime';
}
