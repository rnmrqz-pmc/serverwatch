<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PrometheusTargetController extends Controller
{
    private const EXPORTER_PORTS = [
        'node' => 9100,
        'mysql' => 9104,
        'postgres' => 9187,
    ];

    public function index(string $exporter): JsonResponse
    {
        if (! array_key_exists($exporter, self::EXPORTER_PORTS)) {
            return response()->json([
                'message' => 'Unsupported Prometheus target exporter.',
            ], 404);
        }

        $port = self::EXPORTER_PORTS[$exporter];

        $groups = Server::query()
            ->select(['ip', 'env', 'role'])
            ->orderBy('ip')
            ->get()
            ->groupBy(fn (Server $server) => $server->env)
            ->map(fn ($servers, string $env) => [
                'targets' => $servers
                    ->map(fn (Server $server) => $this->targetFor($server->ip, $port))
                    ->values()
                    ->all(),
                'labels' => [
                    'env' => $env,
                    'exporter' => $exporter,
                ],
            ])
            ->values()
            ->all();

        return response()->json($groups);
    }

    private function targetFor(string $instance, int $port): string
    {
        $host = preg_replace('/:\d+$/', '', $instance);

        return "{$host}:{$port}";
    }
}
