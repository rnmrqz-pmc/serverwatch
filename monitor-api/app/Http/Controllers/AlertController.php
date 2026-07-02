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
        if (!request()->user()->hasPermission('incidents', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view alert incidents.'], 403);
        }

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

            // Merge cache-stored dynamic threshold alerts
            $thresholdAlerts = \Illuminate\Support\Facades\Cache::get('active_threshold_breaches', []);
            $formatted = $formatted->concat($thresholdAlerts);

            return response()->json($formatted);
        } catch (\Exception $e) {
            $thresholdAlerts = \Illuminate\Support\Facades\Cache::get('active_threshold_breaches', []);
            $errors = collect([
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
            ])->concat($thresholdAlerts);

            return response()->json($errors);
        }
    }

    public function active(): JsonResponse
    {
        if (!request()->user()->hasPermission('incidents', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view alert incidents.'], 403);
        }

        return $this->index(); // Reuses index with mock data filtering if needed
    }
}
