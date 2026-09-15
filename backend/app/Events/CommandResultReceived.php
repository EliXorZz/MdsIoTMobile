<?php

namespace App\Events;

use App\Data\CommandResultData;
use Illuminate\Foundation\Events\Dispatchable;

class CommandResultReceived
{
    use Dispatchable;

    public function __construct(public readonly CommandResultData $result) {}
}
