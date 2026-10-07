<?php

namespace App\Prometheus;

use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Summary;
use Spatie\Prometheus\Adapters\LaravelCacheAdapter;

/**
 * LaravelCacheAdapter::collect() fetches data from Redis but discards the return value,
 * leaving $this->counters/$this->gauges empty when parent::collect() runs.
 * This subclass fixes the assignment.
 */
class FixedLaravelCacheAdapter extends LaravelCacheAdapter
{
    /** @return MetricFamilySamples[] */
    public function collect(bool $sortMetrics = true): array
    {
        $this->counters = $this->fetch(Counter::TYPE);
        $this->gauges = $this->fetch(Gauge::TYPE);
        $this->histograms = $this->fetch(Histogram::TYPE);
        $this->summaries = $this->fetch(Summary::TYPE);

        return parent::collect($sortMetrics);
    }
}
