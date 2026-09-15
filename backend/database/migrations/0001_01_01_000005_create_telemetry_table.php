<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry', function (Blueprint $table) {
            $table->timestampTz('observed_at');
            $table->string('device_id');
            $table->string('room_id');
            $table->string('message_id');
            $table->unique(['message_id', 'observed_at']);
            $table->float('temperature');
            $table->integer('co2');

            $table->index(['device_id', 'observed_at']);
        });

        DB::statement("SELECT create_hypertable('telemetry', 'observed_at')");
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry');
    }
};
