<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Server;
use App\Services\DirectMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class DirectMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fallback_to_ssh_metrics_when_prometheus_is_down()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'name'         => 'fallback-node',
            'ip'           => '1.1.1.1',
            'ssh_user'     => 'test-user',
            'ssh_password' => 'test-pass',
            'ssh_port'     => 22,
        ]);

        // Mock DirectMonitoringService
        $mockDirectMonitor = Mockery::mock(DirectMonitoringService::class);
        $mockDirectMonitor->shouldReceive('getServerMetricsViaSsh')
            ->once()
            ->with(Mockery::on(function ($arg) use ($server) {
                return $arg->id === $server->id;
            }))
            ->andReturn([
                'instance'  => '1.1.1.1',
                'cpu'       => 45.5,
                'cpu_cores' => 4,
                'memory'    => ['total' => 8000, 'used' => 4000, 'percent' => 50.0],
                'disk'      => ['total' => 100000, 'used' => 20000, 'percent' => 20.0],
                'uptime'    => 3600,
                'load'      => 1.25,
                'databases' => [],
            ]);

        $this->app->instance(DirectMonitoringService::class, $mockDirectMonitor);

        // Fetch servers list. Prometheus up query will fail/return 0 in tests, so it should trigger direct SSH
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/servers');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('up', $data[0]['status']); // Status overridden to up
        $this->assertEquals(45.5, $data[0]['metrics']['cpu']);
        $this->assertEquals(1.25, $data[0]['metrics']['load']);
    }

    public function test_fallback_to_direct_db_when_prometheus_db_exporter_is_missing()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'name'        => 'db-node',
            'ip'          => '2.2.2.2',
            'db_type'     => 'mysql',
            'db_host'     => 'localhost',
            'db_user'     => 'root',
            'db_password' => 'secret',
        ]);

        // Mock DirectMonitoringService to return DB stats
        $mockDirectMonitor = Mockery::mock(DirectMonitoringService::class);
        $mockDirectMonitor->shouldReceive('getDatabaseMetrics')
            ->once()
            ->with(Mockery::on(function ($arg) use ($server) {
                return $arg->id === $server->id;
            }))
            ->andReturn([
                'type'        => 'mysql',
                'health'      => 'healthy',
                'size_bytes'  => 50000000,
                'connections' => 12,
                'version'     => '8.0.25',
            ]);

        $this->app->instance(DirectMonitoringService::class, $mockDirectMonitor);

        // Mock Prometheus to return server metrics but NO database metrics
        $mockPrometheus = Mockery::mock(\App\Services\PrometheusService::class);
        $mockPrometheus->shouldReceive('query')
            ->andReturn([['value' => [0, '1.0']]]); // Server is UP
        $mockPrometheus->shouldReceive('getServerMetrics')
            ->andReturn([
                'cpu'       => 10.0,
                'cpu_cores' => 2,
                'memory'    => ['total' => 4000, 'used' => 1000, 'percent' => 25.0],
                'disk'      => ['total' => 50000, 'used' => 10000, 'percent' => 20.0],
                'uptime'    => 1200,
                'load'      => 0.5,
                'databases' => [], // Empty databases!
            ]);

        $this->app->instance(\App\Services\PrometheusService::class, $mockPrometheus);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/servers');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals('up', $data[0]['status']);
        $this->assertCount(1, $data[0]['metrics']['databases']);
        $this->assertEquals('mysql', $data[0]['metrics']['databases'][0]['type']);
        $this->assertEquals('healthy', $data[0]['metrics']['databases'][0]['health']);
        $this->assertEquals(12, $data[0]['metrics']['databases'][0]['connections']);
    }
}
