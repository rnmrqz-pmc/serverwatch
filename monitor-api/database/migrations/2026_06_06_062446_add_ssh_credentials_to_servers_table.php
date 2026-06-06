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
        Schema::table('servers', function (Blueprint $table) {
            $table->string('ssh_user')->nullable()->after('db_name');
            $table->unsignedSmallInteger('ssh_port')->nullable()->default(22)->after('ssh_user');
            // AES-256 encrypted password — never returned to the client in plain text
            $table->text('ssh_password')->nullable()->after('ssh_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['ssh_user', 'ssh_port', 'ssh_password']);
        });
    }
};
