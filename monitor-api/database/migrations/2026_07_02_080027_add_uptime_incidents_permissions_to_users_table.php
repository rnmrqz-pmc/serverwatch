<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fetch all users and update their permissions JSON
        $users = \DB::table('users')->get();
        foreach ($users as $user) {
            if ($user->permissions) {
                $perms = json_decode($user->permissions, true);
                if (!isset($perms['uptime'])) {
                    $perms['uptime'] = ['view'];
                }
                if (!isset($perms['incidents'])) {
                    $perms['incidents'] = ['view'];
                }
                \DB::table('users')
                    ->where('id', $user->id)
                    ->update(['permissions' => json_encode($perms)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Strip out the uptime and incidents permission keys
        $users = \DB::table('users')->get();
        foreach ($users as $user) {
            if ($user->permissions) {
                $perms = json_decode($user->permissions, true);
                unset($perms['uptime']);
                unset($perms['incidents']);
                \DB::table('users')
                    ->where('id', $user->id)
                    ->update(['permissions' => json_encode($perms)]);
            }
        }
    }
};
