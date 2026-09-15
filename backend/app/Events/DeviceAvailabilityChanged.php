<?php

namespace App\Events;

use App\Data\AvailabilityData;
use Illuminate\Foundation\Events\Dispatchable;

class DeviceAvailabilityChanged
{
    use Dispatchable;

    public function __construct(public readonly AvailabilityData $availability) {}
}
