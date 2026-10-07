<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SSEService
{
    private const CHANNEL = 'sse';

    private const READ_TIMEOUT = 10.0;

    public function stream(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->write("retry: 3000\n\n");

        while (true) {
            if (connection_aborted()) {
                break;
            }

            $connection = null;

            try {
                $connection = Redis::connection();
                $client = $connection->client();
                $client->setOption(\Redis::OPT_READ_TIMEOUT, self::READ_TIMEOUT);

                $client->subscribe([self::CHANNEL], function (\Redis $r, string $channel, string $message): void {
                    if (connection_aborted()) {
                        $r->unsubscribe();
                        return;
                    }
                    $this->write("data: {$message}\n\n");
                });
            } catch (\Throwable) {
                // subscribe() timed out, connection refused, or connection broke
            } finally {
                try {
                    $connection?->disconnect();
                } catch (\Throwable) {
                }
                Redis::purge('default');
            }

            if (connection_aborted()) {
                break;
            }

            $this->write(": keepalive\n\n");
        }
    }

    private function write(string $data): void
    {
        echo $data;
        flush();
    }
}
