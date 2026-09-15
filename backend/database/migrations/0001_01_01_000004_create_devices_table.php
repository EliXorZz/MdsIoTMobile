<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->string('device_id')->primary();
            $table->string('room_id')->nullable();
            $table->boolean('ventilation')->nullable();
            $table->string('boot_id')->nullable();
            $table->boolean('online')->default(false);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
