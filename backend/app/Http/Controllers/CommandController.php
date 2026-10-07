<?php

namespace App\Http\Controllers;

use App\Actions\SendCommand;
use App\Http\Requests\SendCommandRequest;
use App\Http\Resources\CommandResource;
use App\Models\Device;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommandController extends Controller
{
    public function index(Device $device): AnonymousResourceCollection
    {
        return CommandResource::collection(
            $device->commands()->orderByDesc('issued_at')->paginate(20)
        );
    }

    public function store(SendCommandRequest $request, Device $device): CommandResource
    {
        $data = $request->validated();
        $command = app(SendCommand::class)->execute($device, $data['action'], $data['params']);

        return CommandResource::make($command);
    }
}
