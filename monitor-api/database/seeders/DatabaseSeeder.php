<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insertOrIgnore([
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin',
                'email' => 'ron-ron.marquez@powermaccenter.com',
                'password' => Hash::make('Admin@123'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $configuredServers = config('monitoring.servers', []);

        foreach ($configuredServers as $srv) {
            DB::table('servers')->updateOrInsert(
                ['ip' => $srv['ip']],
                [
                    'name' => $srv['name'],
                    'role' => $srv['role'],
                    'env' => $srv['env'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}