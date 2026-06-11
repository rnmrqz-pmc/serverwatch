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
                'cpu'       => $this->getCpu($prometheusInstance),
                'cpu_cores' => $this->getCpuCores($prometheusInstance),
                'memory'    => $this->getMemory($prometheusInstance),
                'disk'      => $this->getDisk($prometheusInstance),
                'uptime'    => $this->getUptime($prometheusInstance),
                'load'      => $this->getLoad($prometheusInstance),
                'databases' => $this->getDatabases($prometheusInstance),
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

    private function getCpuCores(string $instance): int
    {
        // Count distinct CPUs by counting unique (cpu, mode=idle) time series
        $result = $this->query(
            "count(node_cpu_seconds_total{mode=\"idle\",instance=\"{$instance}\"})"
        );
        return (int)($result[0]['value'][1] ?? 0);
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

    // ─── Database Detection ───────────────────────────────────────────────────

    /**
     * Detect databases running on this server.
     *
     * Strategy (tried in order):
     *   1. Direct match  — query pg_up/mysql_up where instance = "<ip>:9187|9104"
     *      Works for production servers where the DB exporter runs on the same host.
     *   2. Global discovery — fetch ALL pg_up / mysql_up results from Prometheus
     *      and associate the first unmatched exporter with this server when the
     *      server is identified by a Docker service hostname (e.g. "node-exporter").
     *      This handles the dev stack where postgres-exporter is a separate container.
     */
    private function getDatabases(string $instance): array
    {
        $ipOrHost = preg_replace('/:\d+$/', '', $instance); // strip port if present

        $databases = [];

        // 1. PostgreSQL Check
        $pgUp = $this->query("pg_up{instance=~\"{$ipOrHost}(:\\\\d+)?\"}");
        if (!empty($pgUp)) {
            $pgInstance = $pgUp[0]['metric']['instance'] ?? "{$ipOrHost}:9187";
            $up = (int)($pgUp[0]['value'][1] ?? 0);
            $databases[] = [
                'type'        => 'postgresql',
                'health'      => $up === 1 ? 'healthy' : 'down',
                'size_bytes'  => $this->getPostgresSize($pgInstance),
                'connections' => $this->getPostgresConnections($pgInstance),
                'version'     => $this->getPostgresVersion($pgInstance),
            ];
        }

        // 2. MySQL / MariaDB Check
        $myUp = $this->query("mysql_up{instance=~\"{$ipOrHost}(:\\\\d+)?\"}");
        if (!empty($myUp)) {
            $myInstance = $myUp[0]['metric']['instance'] ?? "{$ipOrHost}:9104";
            $up = (int)($myUp[0]['value'][1] ?? 0);
            $databases[] = [
                'type'        => $this->detectMysqlFlavour($myInstance),
                'health'      => $up === 1 ? 'healthy' : 'down',
                'size_bytes'  => $this->getMysqlSize($myInstance),
                'connections' => $this->getMysqlConnections($myInstance),
                'version'     => $this->getMysqlVersion($myInstance),
            ];
        }

        return $databases;
    }

    // ── PostgreSQL helpers ────────────────────────────────────────────────────

    private function getPostgresSize(string $pgInstance): int
    {
        // Sum sizes of all user databases (excludes template0/template1)
        $result = $this->query(
            "sum(pg_database_size_bytes{instance=\"{$pgInstance}\",datname!~\"template.*\"})"
        );
        return (int)($result[0]['value'][1] ?? 0);
    }

    private function getPostgresConnections(string $pgInstance): int
    {
        $result = $this->query(
            "sum(pg_stat_activity_count{instance=\"{$pgInstance}\"})"
        );
        return (int)($result[0]['value'][1] ?? 0);
    }

    private function getPostgresVersion(string $pgInstance): string
    {
        // pg_static exposes a 'short_version' label on the metric
        $result = $this->query("pg_static{instance=\"{$pgInstance}\"}");
        return $result[0]['metric']['short_version'] ?? '';
    }

    // ── MySQL / MariaDB helpers ───────────────────────────────────────────────

    /**
     * Distinguish MariaDB from vanilla MySQL via the version_comment global variable.
     * mysqld_exporter exposes mysql_version_info with a 'version' label.
     */
    private function detectMysqlFlavour(string $myInstance): string
    {
        $result = $this->query("mysql_version_info{instance=\"{$myInstance}\"}");
        $version = strtolower($result[0]['metric']['version'] ?? '');
        return str_contains($version, 'mariadb') ? 'mariadb' : 'mysql';
    }

    private function getMysqlSize(string $myInstance): int
    {
        $result = $this->query(
            "sum(mysql_info_schema_table_size_bytes{instance=\"{$myInstance}\",table_schema!~\"information_schema|performance_schema|mysql|sys\"})"
        );
        // Fallback: if the above metric isn't present, try the simpler global status
        if (empty($result)) {
            $result = $this->query(
                "mysql_global_status_innodb_data_reads{instance=\"{$myInstance}\"}"
            );
        }
        return (int)($result[0]['value'][1] ?? 0);
    }

    private function getMysqlConnections(string $myInstance): int
    {
        $result = $this->query(
            "mysql_global_status_threads_connected{instance=\"{$myInstance}\"}"
        );
        return (int)($result[0]['value'][1] ?? 0);
    }

    private function getMysqlVersion(string $myInstance): string
    {
        $result = $this->query("mysql_version_info{instance=\"{$myInstance}\"}");
        return $result[0]['metric']['version'] ?? '';
    }
}
