<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MqttStats extends Command
{
    protected $signature = 'mqtt:stats';

    protected $description = 'Live subscriber stats from Redis';

    private int $lastLineCount = 0;

    private bool $firstRender = true;

    public function handle(): void
    {
        $this->info('mqtt:stats — Ctrl+C to stop');

        while (true) {
            $this->render();
            sleep(1);
        }
    }

    private function render(): void
    {
        $raw = Redis::hGetAll('mqtt:subscribers');
        $now = microtime(true);
        $subs = array_filter(
            array_map(fn (string $encoded) => json_decode($encoded, true), $raw ?: []),
            fn (?array $subscriber) => $subscriber && ($now - ($subscriber['updated_at'] ?? 0)) < 60
        );

        $lines = [];
        $lines[] = sprintf("\033[1mmqtt:stats\033[0m");
        $lines[] = '';
        $lines[] = sprintf("  \033[90m%-30s %10s %8s %8s %10s %8s\033[0m", 'Subscriber', 'Reçus', 'Rejetés', 'msg/s', 'En DB', 'Lag');
        $lines[] = '  '.str_repeat('─', 78);

        if (empty($subs)) {
            $lines[] = "  \033[90mAucun subscriber actif\033[0m";
        } else {
            foreach ($subs as $subscriber) {
                $lagColor = ($subscriber['lag'] ?? 0) > 0 ? '33' : '32';
                $rejectedColor = ($subscriber['rejected'] ?? 0) > 0 ? '31' : '90';
                $lines[] = sprintf(
                    "  %-30s %10s \033[%sm%8s\033[0m \033[32m%8s\033[0m %10s \033[%sm%8s\033[0m",
                    $subscriber['id'],
                    number_format($subscriber['received']),
                    $rejectedColor,
                    number_format($subscriber['rejected'] ?? 0),
                    number_format($subscriber['rate']),
                    number_format($subscriber['db']),
                    $lagColor,
                    number_format($subscriber['lag'] ?? 0)
                );
            }
        }

        if ($this->firstRender) {
            $this->firstRender = false;
        } else {
            $this->output->write(sprintf("\033[%dA", $this->lastLineCount));
        }

        foreach ($lines as $line) {
            $this->output->write($line."\033[K\n");
        }

        $this->lastLineCount = count($lines);
    }
}
