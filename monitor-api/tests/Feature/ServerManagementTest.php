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
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'instance',
                    'role',
                    'env',
                    'status',
                    'uptime_pct',
                    'uptime_1h_pct',
                    'history',
                    'history_1h',
                    'metrics',
                ]
            ]);

        $this->assertIsArray($response->json()[0]['history']);
        $this->assertNotEmpty($response->json()[0]['history']);
        $this->assertIsArray($response->json()[0]['history_1h']);
        $this->assertNotEmpty($response->json()[0]['history_1h']);
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

    public function test_authenticated_user_can_update_ssh_credentials()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'ip' => '1.2.3.4',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/servers/{$server->id}/ssh-credentials", [
            'ssh_user'     => 'deployer',
            'ssh_port'     => 2222,
            'ssh_password' => 'secret-password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message'             => 'SSH credentials updated successfully.',
                'ssh_user'            => 'deployer',
                'ssh_port'            => 2222,
                'has_ssh_credentials' => true,
            ]);

        $this->assertDatabaseHas('servers', [
            'id'       => $server->id,
            'ssh_user' => 'deployer',
            'ssh_port' => 2222,
        ]);

        // Verify it's actually encrypted in the db
        $rawServer = \DB::table('servers')->where('id', $server->id)->first();
        $this->assertNotEquals('secret-password', $rawServer->ssh_password);
        $this->assertStringContainsString('eyJ', $rawServer->ssh_password); // Laravel encrypted payload contains JSON base64
    }

    public function test_authenticated_user_can_clear_ssh_credentials()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'ip'           => '1.2.3.4',
            'ssh_user'     => 'olduser',
            'ssh_port'     => 22,
            'ssh_password' => 'oldpass',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/servers/{$server->id}/ssh-credentials", [
            'ssh_user' => null,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ssh_user'            => null,
                'ssh_port'            => 22, // default return mapping
                'has_ssh_credentials' => false,
            ]);

        $this->assertDatabaseHas('servers', [
            'id'           => $server->id,
            'ssh_user'     => null,
            'ssh_port'     => null,
            'ssh_password' => null,
        ]);
    }

    public function test_ssh_password_is_hidden_in_json_responses()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'ip'           => '1.2.3.4',
            'ssh_user'     => 'deployer',
            'ssh_port'     => 2222,
            'ssh_password' => 'secret-password',
        ]);

        // List endpoint
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/servers');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayNotHasKey('ssh_password', $data[0]);

        // Show endpoint
        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/servers/{$server->id}");
        $response->assertStatus(200);
        $response->assertJsonMissing(['ssh_password']);
    }
}
