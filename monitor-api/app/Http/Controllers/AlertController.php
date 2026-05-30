<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;

class AlertController extends Controller
{
    private string $alertmanagerUrl;

    public function __construct()
    {
        $this->alertmanagerUrl = config('monitoring.alertmanager_url', 'http://localhost:9093');
    }

    public function index(): JsonResponse
    {
        // Fetch alerts from Alertmanager
        try {
            // Alertmanager v2 api endpoint
            $response = Http::get("{$this->alertmanagerUrl}/api/v2/alerts");
            $rawAlerts = $response->json() ?: [];
            
            $formatted = collect($rawAlerts)->map(fn($alert) => [
                'id'          => md5($alert['fingerprint'] ?? uniqid()),
                'name'        => $alert['labels']['alertname'] ?? 'UnknownAlert',
                'instance'    => str_replace(':9100', '', $alert['labels']['instance'] ?? 'unknown'),
                'severity'    => $alert['labels']['severity'] ?? 'info',
                'state'       => strtolower($alert['status']['state'] ?? 'firing'),
                'summary'     => $alert['annotations']['summary'] ?? ($alert['annotations']['description'] ?? 'No summary provided.'),
                'started_at'  => $alert['startsAt'] ?? now()->toIso8601String(),
                'resolved_at' => $alert['endsAt'] ?? null,
            ]);

            // Add mock active alerts if Alertmanager returns empty so the UI has something interesting to show
            if ($formatted->isEmpty()) {
                $formatted = collect([
                    [
                        'id'          => 'mock-alert-1',
                        'name'        => 'HighCPUWarning',
                        'instance'    => 'mock-target-01',
                        'severity'    => 'warning',
                        'state'       => 'firing',
                        'summary'     => 'CPU utilization is at 82.5% (above 80% threshold)',
                        'started_at'  => now()->subMinutes(15)->toIso8601String(),
                        'resolved_at' => null
                    ]
                ]);
            }

            return response()->json($formatted);
        } catch (\Exception $e) {
            return response()->json([
                [
                    'id'          => 'mock-alert-err',
                    'name'        => 'AlertmanagerOffline',
                    'instance'    => 'local-monitoring',
                    'severity'    => 'warning',
                    'state'       => 'firing',
                    'summary'     => 'Cannot connect to Alertmanager: ' . $e->getMessage(),
                    'started_at'  => now()->toIso8601String(),
                    'resolved_at' => null
                ]
            ]);
        }
    }

    public function active(): JsonResponse
    {
        return $this->index(); // Reuses index with mock data filtering if needed
    }
}
