<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SSEService
{
    private const CHANNEL = 'sse';

    private const READ_TIMEOUT = 20.0;

    public function stream(): void
    {
        set_time_limit(0);
        ignore_user_abort(false);

        $this->write("retry: 5000\n\n");

        while (! connection_aborted()) {
            $connection = Redis::connection();

            try {
                $client = $connection->client();
                $client->setOption(\Redis::OPT_READ_TIMEOUT, self::READ_TIMEOUT);

                $client->subscribe([self::CHANNEL], function (\Redis $r, string $channel, string $message): void {
                    $this->write("data: {$message}\n\n");
                    if (connection_aborted()) {
                        $r->unsubscribe();
                    }
                });
            } catch (\Throwable) {
                if (connection_aborted()) {
                    break;
                }
                $this->write(": keepalive\n\n");
            } finally {
                $connection->disconnect();
            }
        }
    }

    private function write(string $data): void
    {
        echo $data;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
