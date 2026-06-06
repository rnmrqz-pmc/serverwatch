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
            $table->integer('cpu_threshold_info')->default(60);
            $table->integer('cpu_threshold_warning')->default(70);
            $table->integer('cpu_threshold_critical')->default(90);

            $table->integer('ram_threshold_info')->default(60);
            $table->integer('ram_threshold_warning')->default(70);
            $table->integer('ram_threshold_critical')->default(90);

            $table->integer('disk_threshold_info')->default(60);
            $table->integer('disk_threshold_warning')->default(70);
            $table->integer('disk_threshold_critical')->default(90);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'cpu_threshold_info',
                'cpu_threshold_warning',
                'cpu_threshold_critical',
                'ram_threshold_info',
                'ram_threshold_warning',
                'ram_threshold_critical',
                'disk_threshold_info',
                'disk_threshold_warning',
                'disk_threshold_critical',
            ]);
        });
    }
};
