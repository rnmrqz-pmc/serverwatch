<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PrometheusService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('monitoring.prometheus_url', 'http://localhost:9091');
    }

    public function query(string $query): array
    {
        $response = Http::get("{$this->baseUrl}/api/v1/query", [
            'query' => $query,
        ]);
        return $response->json('data.result', []);
    }

    public function queryRange(string $query, int $start, int $end, string $step = '60'): array
    {
        $response = Http::get("{$this->baseUrl}/api/v1/query_range", [
            'query' => $query,
            'start' => $start,
            'end'   => $end,
            'step'  => $step,
        ]);
        return $response->json('data.result', []);
    }

    public function getServerMetrics(string $instance): array
    {
        // Prometheus instance label usually includes the port. If it doesn't, we append it.
        $prometheusInstance = str_contains($instance, ':') ? $instance : "{$instance}:9100";

        return Cache::remember("metrics:{$prometheusInstance}", 10, function () use ($prometheusInstance) {
            return [
                'cpu'    => $this->getCpu($prometheusInstance),
                'memory' => $this->getMemory($prometheusInstance),
                'disk'   => $this->getDisk($prometheusInstance),
                'uptime' => $this->getUptime($prometheusInstance),
                'load'   => $this->getLoad($prometheusInstance),
            ];
        });
    }

    private function getCpu(string $instance): float
    {
        $result = $this->query(
            "100 - (avg by(instance)(rate(node_cpu_seconds_total{mode=\"idle\",instance=\"{$instance}\"}[5m])) * 100)"
        );
        return round((float)($result[0]['value'][1] ?? 0), 2);
    }

    private function getMemory(string $instance): array
    {
        $totalResult = $this->query("node_memory_MemTotal_bytes{instance=\"{$instance}\"}");
        $availResult = $this->query("node_memory_MemAvailable_bytes{instance=\"{$instance}\"}");
        
        $total = $totalResult[0]['value'][1] ?? 0;
        $avail = $availResult[0]['value'][1] ?? 0;
        $used  = $total - $avail;
        return [
            'total'   => (int)$total,
            'used'    => (int)$used,
            'percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    private function getDisk(string $instance): array
    {
        // Inside docker node-exporter mount points might vary, but typically "/" is mounted.
        $totalResult = $this->query("node_filesystem_size_bytes{instance=\"{$instance}\",mountpoint=\"/\"}");
        $freeResult  = $this->query("node_filesystem_free_bytes{instance=\"{$instance}\",mountpoint=\"/\"}");
        
        // Fallback to any mountpoint if "/" is empty (which can happen under different OS profiles)
        if (empty($totalResult)) {
            $totalResult = $this->query("node_filesystem_size_bytes{instance=\"{$instance}\"}");
            $freeResult  = $this->query("node_filesystem_free_bytes{instance=\"{$instance}\"}");
        }

        $total = $totalResult[0]['value'][1] ?? 0;
        $free  = $freeResult[0]['value'][1] ?? 0;
        $used  = $total - $free;
        return [
            'total'   => (int)$total,
            'used'    => (int)$used,
            'percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    private function getUptime(string $instance): float
    {
        $result = $this->query("node_time_seconds{instance=\"{$instance}\"} - node_boot_time_seconds{instance=\"{$instance}\"}");
        return round((float)($result[0]['value'][1] ?? 0));
    }

    private function getLoad(string $instance): float
    {
        $result = $this->query("node_load1{instance=\"{$instance}\"}");
        return round((float)($result[0]['value'][1] ?? 0), 2);
    }
}
