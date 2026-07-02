<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials()
    {
        $user = User::factory()->create([
            'email' => 'admin@serverwatch.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@serverwatch.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user'])
            ->assertJson([
                'user' => [
                    'email' => 'admin@serverwatch.com',
                ]
            ]);
    }

    public function test_user_cannot_login_with_incorrect_password()
    {
        $user = User::factory()->create([
            'email' => 'admin@serverwatch.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@serverwatch.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_monitoring_endpoints_are_protected()
    {
        $response = $this->getJson('/api/v1/servers');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_servers()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/servers');
        
        $response->assertStatus(200);
    }

    public function test_admin_can_perform_user_management_crud()
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.credentials',
                \Mockery::on(fn($data) => str_contains($data['bodyText'], 'created')),
                \Mockery::on(fn($callback) => true)
            );

        $admin = User::factory()->create();

        // 1. List Users
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/users');
        $response->assertStatus(200)
            ->assertJsonCount(1); // just the admin

        // 2. Create User
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'John Doe',
            'email' => 'john@serverwatch.com',
        ]);
        $response->assertStatus(201)
            ->assertJson([
                'name' => 'John Doe',
                'email' => 'john@serverwatch.com',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'john@serverwatch.com']);
        $newUser = User::where('email', 'john@serverwatch.com')->first();
        $this->assertNotEmpty($newUser->password);

        // 3. Update User
        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/users/{$newUser->id}", [
            'name' => 'John Smith',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'name' => 'John Smith',
            ]);

        // 4. Delete User
        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/users/{$newUser->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['email' => 'john@serverwatch.com']);
    }

    public function test_create_user_rolls_back_if_mail_fails()
    {
        $admin = User::factory()->create();

        // Mock Mail to throw exception
        \Illuminate\Support\Facades\Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('SMTP connection timeout'));

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'Failed User',
            'email' => 'failed@serverwatch.com',
        ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('Failed to send', $response->json()['message']);

        // Verify user was deleted (rolled back)
        $this->assertDatabaseMissing('users', ['email' => 'failed@serverwatch.com']);
    }

    public function test_admin_can_reset_user_password()
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('send')
            ->once()
            ->with(
                'emails.credentials',
                \Mockery::on(fn($data) => str_contains($data['bodyText'], 'reset')),
                \Mockery::on(fn($callback) => true)
            );

        $admin = User::factory()->create();
        $user = User::factory()->create([
            'email' => 'user@serverwatch.com',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/users/{$user->id}/reset-password");

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // Verify password changed
        $user->refresh();
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    public function test_password_reset_rolls_back_if_mail_fails()
    {
        $admin = User::factory()->create();
        $user = User::factory()->create([
            'email' => 'user@serverwatch.com',
            'password' => Hash::make('old-password'),
        ]);

        // Mock Mail to throw exception
        \Illuminate\Support\Facades\Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('SMTP connection timeout'));

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/users/{$user->id}/reset-password");

        $response->assertStatus(500);
        $this->assertStringContainsString('Failed to send', $response->json()['message']);

        // Verify password is still the old one (rolled back)
        $user->refresh();
        $this->assertTrue(Hash::check('old-password', $user->password));
    }

    public function test_user_cannot_delete_themselves()
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/users/{$admin->id}");
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_can_change_password_with_correct_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-secret-password'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-secret-password',
            'new_password' => 'new-secret-password',
            'new_password_confirmation' => 'new-secret-password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password changed successfully.']);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-password', $user->password));
    }

    public function test_user_cannot_change_password_with_incorrect_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-secret-password'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/change-password', [
            'current_password' => 'incorrect-current-password',
            'new_password' => 'new-secret-password',
            'new_password_confirmation' => 'new-secret-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('old-secret-password', $user->password));
    }

    public function test_user_cannot_change_password_with_mismatched_confirmation()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-secret-password'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-secret-password',
            'new_password' => 'new-secret-password',
            'new_password_confirmation' => 'mismatched-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('old-secret-password', $user->password));
    }

    public function test_user_permissions_are_enforced_on_users_index()
    {
        $user = User::factory()->create([
            'permissions' => [
                'servers' => ['view'],
                'users' => [],
                'maintenance' => []
            ]
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/users');
        $response->assertStatus(403);
    }

    public function test_user_permissions_are_enforced_on_maintenance_settings()
    {
        $user = User::factory()->create([
            'permissions' => [
                'servers' => ['view'],
                'users' => ['view'],
                'maintenance' => []
            ]
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/maintenance/smtp');
        $response->assertStatus(403);
    }

    public function test_user_permissions_are_enforced_on_server_threshold_updates()
    {
        $server = \App\Models\Server::create([
            'name' => 'Test Server',
            'ip' => '127.0.0.1',
            'role' => 'Web',
            'env' => 'production'
        ]);

        $user = User::factory()->create([
            'permissions' => [
                'servers' => ['view'],
                'users' => ['view'],
                'maintenance' => ['view']
            ]
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/servers/{$server->id}/thresholds", [
            'cpu_threshold_info' => 50,
            'cpu_threshold_warning' => 60,
            'cpu_threshold_critical' => 70,
            'ram_threshold_info' => 50,
            'ram_threshold_warning' => 60,
            'ram_threshold_critical' => 70,
            'disk_threshold_info' => 50,
            'disk_threshold_warning' => 60,
            'disk_threshold_critical' => 70,
        ]);
        $response->assertStatus(403);
    }

    public function test_user_permissions_are_enforced_on_uptime_history()
    {
        $user = User::factory()->create([
            'permissions' => [
                'servers' => ['view'],
                'users' => ['view'],
                'maintenance' => ['view'],
                'uptime' => [],
                'incidents' => ['view']
            ]
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/uptime/127.0.0.1');
        $response->assertStatus(403);
    }

    public function test_user_permissions_are_enforced_on_system_incidents()
    {
        $user = User::factory()->create([
            'permissions' => [
                'servers' => ['view'],
                'users' => ['view'],
                'maintenance' => ['view'],
                'uptime' => ['view'],
                'incidents' => []
            ]
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/alerts');
        $response->assertStatus(403);
    }
}
