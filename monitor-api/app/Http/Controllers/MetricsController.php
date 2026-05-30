<?php

namespace App\Http\Controllers;

use App\Services\PrometheusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MetricsController extends Controller
{
    public function __construct(private PrometheusService $prometheus) {}

    public function current(string $instance): JsonResponse
    {
        try {
            $metrics = $this->prometheus->getServerMetrics($instance);
            return response()->json($metrics);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve metrics: ' . $e->getMessage()], 500);
        }
    }

    public function history(Request $request, string $instance): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);
        $start = now()->subHours($hours)->timestamp;
        $end = now()->timestamp;
        
        // Step dynamically computed based on range to avoid returning too many points
        $step = max(60, $hours * 15); 

        $prometheusInstance = str_contains($instance, ':') ? $instance : "{$instance}:9100";

        // Query templates
        $cpuQuery = "100 - (avg by(instance)(rate(node_cpu_seconds_total{mode=\"idle\",instance=\"{$prometheusInstance}\"}[5m])) * 100)";
        
        $memQuery = "((node_memory_MemTotal_bytes{instance=\"{$prometheusInstance}\"} - node_memory_MemAvailable_bytes{instance=\"{$prometheusInstance}\"}) / node_memory_MemTotal_bytes{instance=\"{$prometheusInstance}\"}) * 100";
        
        $diskQuery = "((node_filesystem_size_bytes{instance=\"{$prometheusInstance}\",mountpoint=\"/\"} - node_filesystem_free_bytes{instance=\"{$prometheusInstance}\",mountpoint=\"/\"}) / node_filesystem_size_bytes{instance=\"{$prometheusInstance}\",mountpoint=\"/\"}) * 100";

        try {
            $cpuResult = $this->prometheus->queryRange($cpuQuery, $start, $end, (string)$step);
            $memResult = $this->prometheus->queryRange($memQuery, $start, $end, (string)$step);
            $diskResult = $this->prometheus->queryRange($diskQuery, $start, $end, (string)$step);

            $formatted = [
                'cpu'  => $this->formatRangePoints($cpuResult),
                'mem'  => $this->formatRangePoints($memResult),
                'disk' => $this->formatRangePoints($diskResult),
            ];

            return response()->json($formatted);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve history: ' . $e->getMessage()], 500);
        }
    }

    private function formatRangePoints(array $result): array
    {
        if (empty($result) || empty($result[0]['values'])) {
            return [];
        }

        return collect($result[0]['values'])->map(fn($point) => [
            'timestamp' => (int) $point[0],
            'value'     => round((float) $point[1], 2),
        ])->toArray();
    }
}
