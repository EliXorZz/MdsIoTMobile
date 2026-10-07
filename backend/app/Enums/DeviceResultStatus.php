<?php

namespace App\Enums;

enum DeviceResultStatus: string
{
    case Executed = 'executed';
    case Rejected = 'rejected';
}
