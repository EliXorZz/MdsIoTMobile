<?php

namespace App\Events;

use App\Data\DeviceStateData;
use Illuminate\Foundation\Events\Dispatchable;

class DeviceStateReceived
{
    use Dispatchable;

    public function __construct(public readonly DeviceStateData $state) {}
}
