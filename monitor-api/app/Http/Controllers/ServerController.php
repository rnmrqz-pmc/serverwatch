<?php

namespace App\Http\Controllers;

use App\Services\PrometheusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ServerController extends Controller
{
    public function __construct(private PrometheusService $prometheus) {}

    public function index(): JsonResponse
    {
        $configuredServers = config('monitoring.servers', []);

        $servers = collect($configuredServers)->map(function ($server) {
            $instance = $server['ip'];
            $prometheusInstance = str_contains($instance, ':') ? $instance : "{$instance}:9100";

            // Query current 'up' metric to find status
            $upQuery = $this->prometheus->query("up{instance=\"{$prometheusInstance}\"}");
            $isUp = ((float)($upQuery[0]['value'][1] ?? 0)) === 1.0;

            // Fetch metrics
            $metrics = [];
            if ($isUp) {
                try {
                    $metrics = $this->prometheus->getServerMetrics($instance);
                } catch (\Exception $e) {
                    // Failed to load metrics but node might still be up
                }
            }

            // Fetch 90d average uptime
            $uptimePct = 100.0;
            try {
                $uptimeRange = $this->prometheus->query("avg_over_time(up{instance=\"{$prometheusInstance}\"}[90d])");
                $uptimePct = round((float)($uptimeRange[0]['value'][1] ?? 1.0) * 100, 2);
            } catch (\Exception $e) {
                // Fallback to 100%
            }

            return [
                'name'       => $server['name'],
                'instance'   => $server['ip'],
                'role'       => $server['role'],
                'env'        => $server['env'],
                'status'     => $isUp ? 'up' : 'down',
                'uptime_pct' => $uptimePct,
                'metrics'    => $metrics ?: null,
            ];
        });

        return response()->json($servers);
    }

    public function show(string $instance): JsonResponse
    {
        $configuredServers = config('monitoring.servers', []);
        $server = collect($configuredServers)->firstWhere('ip', $instance);

        if (!$server) {
            return response()->json(['message' => 'Server not found'], 404);
        }

        $prometheusInstance = str_contains($instance, ':') ? $instance : "{$instance}:9100";
        $upQuery = $this->prometheus->query("up{instance=\"{$prometheusInstance}\"}");
        $isUp = ((float)($upQuery[0]['value'][1] ?? 0)) === 1.0;

        $metrics = [];
        if ($isUp) {
            $metrics = $this->prometheus->getServerMetrics($instance);
        }

        return response()->json([
            'name'     => $server['name'],
            'instance' => $server['ip'],
            'role'     => $server['role'],
            'env'      => $server['env'],
            'status'   => $isUp ? 'up' : 'down',
            'metrics'  => $metrics,
        ]);
    }
}
