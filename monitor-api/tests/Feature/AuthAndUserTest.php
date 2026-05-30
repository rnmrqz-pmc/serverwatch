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
        $admin = User::factory()->create();

        // 1. List Users
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/users');
        $response->assertStatus(200)
            ->assertJsonCount(1); // just the admin

        // 2. Create User
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'John Doe',
            'email' => 'john@serverwatch.com',
            'password' => 'newpassword123',
        ]);
        $response->assertStatus(201)
            ->assertJson([
                'name' => 'John Doe',
                'email' => 'john@serverwatch.com',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'john@serverwatch.com']);
        $newUser = User::where('email', 'john@serverwatch.com')->first();

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

    public function test_user_cannot_delete_themselves()
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/users/{$admin->id}");
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
