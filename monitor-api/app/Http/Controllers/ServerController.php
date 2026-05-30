<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\PrometheusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    public function __construct(private PrometheusService $prometheus) {}

    public function index(): JsonResponse
    {
        $servers = Server::all()->map(function ($server) {
            $instance = $server->ip;
            $prometheusInstance = str_contains($instance, ':') ? $instance : "{$instance}:9100";

            try {
                $upQuery = $this->prometheus->query("up{instance=\"{$prometheusInstance}\"}");
                $isUp = ((float)($upQuery[0]['value'][1] ?? 0)) === 1.0;
            } catch (\Exception $e) {
                $isUp = false;
            }

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
                'id'         => $server->id,
                'name'       => $server->name,
                'instance'   => $server->ip,
                'role'       => $server->role,
                'env'        => $server->env,
                'status'     => $isUp ? 'up' : 'down',
                'uptime_pct' => $uptimePct,
                'metrics'    => $metrics ?: null,
            ];
        });

        return response()->json($servers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip'   => 'required|string|max:255|unique:servers,ip',
            'role' => 'required|string|max:255',
            'env'  => 'required|string|in:production,staging,development',
        ]);

        $server = Server::create($validated);

        return response()->json($server, 201);
    }

    public function show(Server $server): JsonResponse
    {
        $instance = $server->ip;
        $prometheusInstance = str_contains($instance, ':') ? $instance : "{$instance}:9100";
        try {
            $upQuery = $this->prometheus->query("up{instance=\"{$prometheusInstance}\"}");
            $isUp = ((float)($upQuery[0]['value'][1] ?? 0)) === 1.0;
        } catch (\Exception $e) {
            $isUp = false;
        }

        $metrics = [];
        if ($isUp) {
            $metrics = $this->prometheus->getServerMetrics($instance);
        }

        return response()->json([
            'id'       => $server->id,
            'name'     => $server->name,
            'instance' => $server->ip,
            'role'     => $server->role,
            'env'      => $server->env,
            'status'   => $isUp ? 'up' : 'down',
            'metrics'  => $metrics,
        ]);
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'ip'   => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('servers')->ignore($server->id),
            ],
            'role' => 'sometimes|required|string|max:255',
            'env'  => 'sometimes|required|string|in:production,staging,development',
        ]);

        $server->update($validated);

        return response()->json($server);
    }

    public function destroy(Server $server): JsonResponse
    {
        $server->delete();

        return response()->json(['message' => 'Server deleted successfully']);
    }
}
