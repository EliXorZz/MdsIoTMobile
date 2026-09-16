<?php

namespace App\Models;

class TelemetryOneMinute extends TelemetryAggregate
{
    protected $table = 'telemetry_1m';

    protected $primaryKey = 'bucket';

    protected $keyType = 'datetime';
}
