<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Server;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => 'admin',
            'email' => 'ron-ron.marquez@powermaccenter.com',
            'password' => \Illuminate\Support\Facades\Hash::make('Admin@123'),
        ]);

        // Seed servers from config
        $configuredServers = config('monitoring.servers', []);
        foreach ($configuredServers as $srv) {
            Server::firstOrCreate(
                ['ip' => $srv['ip']],
                [
                    'name' => $srv['name'],
                    'role' => $srv['role'],
                    'env' => $srv['env'],
                ]
            );
        }
    }
}
