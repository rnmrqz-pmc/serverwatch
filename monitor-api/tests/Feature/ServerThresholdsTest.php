<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Services\PrometheusService;
use Tests\TestCase;

class ServerThresholdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_thresholds()
    {
        $server = Server::create([
            'name' => 'Test Target',
            'ip'   => '192.168.1.5',
            'role' => 'Web Node',
            'env'  => 'production',
        ]);

        $response = $this->putJson("/api/v1/servers/{$server->id}/thresholds", [
            'cpu_threshold_info'      => 60,
            'cpu_threshold_warning'   => 70,
            'cpu_threshold_critical'  => 90,
            'ram_threshold_info'      => 60,
            'ram_threshold_warning'   => 70,
            'ram_threshold_critical'  => 90,
            'disk_threshold_info'     => 60,
            'disk_threshold_warning'  => 70,
            'disk_threshold_critical' => 90,
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_thresholds_with_valid_data()
    {
        $user = User::factory()->create();
        $server = Server::create([
            'name' => 'Test Target',
            'ip'   => '192.168.1.5',
            'role' => 'Web Node',
            'env'  => 'production',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/servers/{$server->id}/thresholds", [
            'cpu_threshold_info'      => 50,
            'cpu_threshold_warning'   => 65,
            'cpu_threshold_critical'  => 85,
            'ram_threshold_info'      => 55,
            'ram_threshold_warning'   => 70,
            'ram_threshold_critical'  => 88,
            'disk_threshold_info'     => 40,
            'disk_threshold_warning'  => 60,
            'disk_threshold_critical' => 80,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Alert thresholds updated successfully.'
            ]);

        $server = $server->fresh();
        $this->assertEquals(50, $server->cpu_threshold_info);
        $this->assertEquals(65, $server->cpu_threshold_warning);
        $this->assertEquals(85, $server->cpu_threshold_critical);

        $this->assertEquals(55, $server->ram_threshold_info);
        $this->assertEquals(70, $server->ram_threshold_warning);
        $this->assertEquals(88, $server->ram_threshold_critical);

        $this->assertEquals(40, $server->disk_threshold_info);
        $this->assertEquals(60, $server->disk_threshold_warning);
        $this->assertEquals(80, $server->disk_threshold_critical);
    }

    public function test_thresholds_validation_rules()
    {
        $user = User::factory()->create();
        $server = Server::create([
            'name' => 'Test Target',
            'ip'   => '192.168.1.5',
            'role' => 'Web Node',
            'env'  => 'production',
        ]);

        // Out of range (min/max checks)
        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/servers/{$server->id}/thresholds", [
            'cpu_threshold_info'      => 0, // invalid < 1
            'cpu_threshold_warning'   => 120, // invalid > 100
            'cpu_threshold_critical'  => 90,
            'ram_threshold_info'      => 60,
            'ram_threshold_warning'   => 70,
            'ram_threshold_critical'  => 90,
            'disk_threshold_info'     => 60,
            'disk_threshold_warning'  => 70,
            'disk_threshold_critical' => 90,
        ]);
        $response->assertStatus(422);

        // Incorrect ordering: warning < info
        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/servers/{$server->id}/thresholds", [
            'cpu_threshold_info'      => 80,
            'cpu_threshold_warning'   => 70, // warning < info
            'cpu_threshold_critical'  => 90,
            'ram_threshold_info'      => 60,
            'ram_threshold_warning'   => 70,
            'ram_threshold_critical'  => 90,
            'disk_threshold_info'     => 60,
            'disk_threshold_warning'  => 70,
            'disk_threshold_critical' => 90,
        ]);
        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'CPU thresholds must satisfy: Info <= Warning <= Critical.']);
    }

    public function test_monitor_thresholds_command_sends_email_and_caches_active_breaches()
    {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $server = Server::create([
            'name' => 'Monitor Target',
            'ip'   => '192.168.1.10',
            'role' => 'DB Node',
            'env'  => 'production',
            'cpu_threshold_info'      => 60,
            'cpu_threshold_warning'   => 70,
            'cpu_threshold_critical'  => 90,
        ]);

        // Clear cache before starting
        Cache::flush();

        // Mock PrometheusService
        $prometheusMock = \Mockery::mock(PrometheusService::class);
        $prometheusMock->shouldReceive('query')
            ->with('up{instance="192.168.1.10:9100"}')
            ->andReturn([['value' => [time(), '1.0']]]);

        $prometheusMock->shouldReceive('getServerMetrics')
            ->with('192.168.1.10')
            ->andReturn([
                'cpu' => 75.0, // warning level (above 70%)
                'memory' => ['percent' => 50.0],
                'disk' => ['percent' => 45.0],
            ]);

        $this->instance(PrometheusService::class, $prometheusMock);

        // Expect warning email for CPU
        Mail::shouldReceive('raw')
            ->once()
            ->with(
                \Mockery::on(function ($content) {
                    return str_contains($content, 'CPU') &&
                           str_contains($content, '75%') &&
                           str_contains($content, '70%') &&
                           str_contains($content, 'WARNING');
                }),
                \Mockery::on(function ($callback) {
                    return true;
                })
            );

        // Run artisan command
        $this->artisan('monitor:thresholds')
            ->assertExitCode(0);

        // Verify state is cached
        $this->assertEquals('warning', Cache::get("server_{$server->id}_CPU_alert_level"));

        // Verify active breaches are stored
        $breaches = Cache::get('active_threshold_breaches');
        $this->assertCount(1, $breaches);
        $this->assertEquals('HighCPUUsage', $breaches[0]['name']);
        $this->assertEquals('warning', $breaches[0]['severity']);
        $this->assertEquals('192.168.1.10', $breaches[0]['instance']);

        // Verify merged into AlertController index list
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/alerts');
        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'HighCPUUsage',
                'severity' => 'warning',
            ]);
    }
}
