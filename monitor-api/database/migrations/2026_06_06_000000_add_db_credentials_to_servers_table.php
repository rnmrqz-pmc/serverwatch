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
            // Database type: mariadb | mysql | postgresql | none
            $table->string('db_type', 20)->default('none')->after('env');
            // Connection host — can be an IP, hostname, or Docker service name
            $table->string('db_host')->nullable()->after('db_type');
            // Port (3306 for MySQL/MariaDB, 5432 for PostgreSQL)
            $table->unsignedSmallInteger('db_port')->nullable()->after('db_host');
            // Monitor user (read-only recommended)
            $table->string('db_user')->nullable()->after('db_port');
            // AES-256 encrypted password — never returned to the client in plain text
            $table->text('db_password')->nullable()->after('db_user');
            // Optional: target database name (null = monitor all)
            $table->string('db_name')->nullable()->after('db_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['db_type', 'db_host', 'db_port', 'db_user', 'db_password', 'db_name']);
        });
    }
};
