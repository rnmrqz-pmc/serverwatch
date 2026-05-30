<?php

namespace Tests\Feature;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrometheusTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_node_targets_grouped_by_environment(): void
    {
        Server::factory()->create([
            'ip' => '172.22.2.174',
            'env' => 'staging',
            'role' => 'Production Web Node',
        ]);

        Server::factory()->create([
            'ip' => '172.22.2.171:9100',
            'env' => 'production',
            'role' => 'Production Web Node',
        ]);

        $response = $this->getJson('/api/prometheus/targets/node');

        $response->assertOk()
            ->assertJsonFragment([
                'targets' => ['172.22.2.171:9100'],
                'labels' => [
                    'env' => 'production',
                    'exporter' => 'node',
                ],
            ])
            ->assertJsonFragment([
                'targets' => ['172.22.2.174:9100'],
                'labels' => [
                    'env' => 'staging',
                    'exporter' => 'node',
                ],
            ]);
    }

    public function test_it_returns_database_exporter_targets(): void
    {
        Server::factory()->create([
            'ip' => '172.22.2.174',
            'env' => 'staging',
        ]);

        $this->getJson('/api/prometheus/targets/mysql')
            ->assertOk()
            ->assertJsonFragment([
                'targets' => ['172.22.2.174:9104'],
                'labels' => [
                    'env' => 'staging',
                    'exporter' => 'mysql',
                ],
            ]);

        $this->getJson('/api/prometheus/targets/postgres')
            ->assertOk()
            ->assertJsonFragment([
                'targets' => ['172.22.2.174:9187'],
                'labels' => [
                    'env' => 'staging',
                    'exporter' => 'postgres',
                ],
            ]);
    }

    public function test_it_rejects_unknown_exporters(): void
    {
        $this->getJson('/api/prometheus/targets/redis')
            ->assertNotFound()
            ->assertJson([
                'message' => 'Unsupported Prometheus target exporter.',
            ]);
    }
}
