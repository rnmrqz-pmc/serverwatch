<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_smtp_settings()
    {
        $response = $this->getJson('/api/v1/maintenance/smtp');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_default_smtp_settings()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/maintenance/smtp');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'mail_host',
                'mail_port',
                'mail_encryption',
                'mail_username',
                'mail_from_address',
                'mail_from_name',
                'has_password',
            ]);
            
        $this->assertFalse($response->json()['has_password']);
    }

    public function test_authenticated_user_can_update_smtp_settings()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/maintenance/smtp', [
            'mail_host'         => 'smtp.mailtrap.io',
            'mail_port'         => 2525,
            'mail_encryption'   => 'tls',
            'mail_username'     => 'test-user',
            'mail_password'     => 'test-pass',
            'mail_from_address' => 'noreply@test.com',
            'mail_from_name'    => 'Test Monitor',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'SMTP Configuration updated successfully!']);

        $this->assertEquals('smtp.mailtrap.io', Setting::get('mail_host'));
        $this->assertEquals('2525', Setting::get('mail_port'));
        $this->assertEquals('tls', Setting::get('mail_encryption'));
        $this->assertEquals('test-user', Setting::get('mail_username'));
        $this->assertEquals('noreply@test.com', Setting::get('mail_from_address'));
        $this->assertEquals('Test Monitor', Setting::get('mail_from_name'));

        // Verify password encryption in DB
        $savedPass = Setting::get('mail_password');
        $this->assertNotEmpty($savedPass);
        $this->assertEquals('test-pass', decrypt($savedPass));

        // Get settings should now report password exists, but hide the value
        $getRes = $this->actingAs($user, 'sanctum')->getJson('/api/v1/maintenance/smtp');
        $getRes->assertStatus(200)
            ->assertJson([
                'mail_host' => 'smtp.mailtrap.io',
                'has_password' => true,
            ]);
            
        $this->assertArrayNotHasKey('mail_password', $getRes->json());
    }

    public function test_authenticated_user_can_test_smtp_settings()
    {
        $user = User::factory()->create();

        $mailerMock = \Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailerMock->shouldReceive('raw')
            ->once()
            ->with(
                \Mockery::on(function ($content) {
                    return str_contains($content, 'SMTP configuration');
                }),
                \Mockery::on(function ($callback) {
                    $message = \Mockery::mock(\Illuminate\Mail\Message::class);
                    $message->shouldReceive('to')->once()->with('admin@dev.com')->andReturnSelf();
                    $message->shouldReceive('subject')->once()->with('ServerWatcher SMTP Test Mail')->andReturnSelf();
                    $callback($message);
                    return true;
                })
            );

        Mail::shouldReceive('mailer')
            ->once()
            ->with('custom_smtp')
            ->andReturn($mailerMock);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/maintenance/smtp/test', [
            'email'             => 'admin@dev.com',
            'mail_host'         => 'smtp.mailtrap.io',
            'mail_port'         => 2525,
            'mail_encryption'   => 'tls',
            'mail_username'     => 'test-user',
            'mail_password'     => 'test-pass',
            'mail_from_address' => 'noreply@test.com',
            'mail_from_name'    => 'Test Monitor',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);
    }
}
