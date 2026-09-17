<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
}
