<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_servers()
    {
        $response = $this->getJson('/api/v1/servers');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_servers()
    {
        $user = User::factory()->create();
        Server::factory()->create([
            'name' => 'test-server',
            'ip' => '1.2.3.4',
            'role' => 'Test Role',
            'env' => 'production',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/servers');
        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_authenticated_user_can_create_server()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/servers', [
            'name' => 'new-server',
            'ip' => '5.6.7.8',
            'role' => 'New Node',
            'env' => 'production',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'new-server',
                'ip' => '5.6.7.8',
            ]);

        $this->assertDatabaseHas('servers', ['ip' => '5.6.7.8']);
    }

    public function test_cannot_create_server_with_existing_ip()
    {
        $user = User::factory()->create();
        Server::factory()->create(['ip' => '1.1.1.1']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/servers', [
            'name' => 'duplicate-ip-server',
            'ip' => '1.1.1.1',
            'role' => 'Any',
            'env' => 'production',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ip']);
    }

    public function test_authenticated_user_can_update_server()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'name' => 'old-name',
            'ip' => '1.1.1.1',
            'role' => 'Old Role',
            'env' => 'development',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/servers/{$server->id}", [
            'name' => 'updated-name',
            'role' => 'Updated Role',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'updated-name',
                'role' => 'Updated Role',
            ]);
    }

    public function test_authenticated_user_can_delete_server()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['ip' => '2.2.2.2']);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/servers/{$server->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('servers', ['ip' => '2.2.2.2']);
    }
}
