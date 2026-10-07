<?php

namespace App\Http\Controllers;

use App\Services\SSEService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SSEController extends Controller
{
    public function stream(SSEService $service): StreamedResponse
    {
        return response()->stream(
            fn () => $service->stream(),
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]
        );
    }
}
