<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commands', function (Blueprint $table) {
            $table->uuid('command_id')->primary();
            $table->string('device_id');
            $table->string('action');
            $table->jsonb('params');
            $table->string('status')->default('PENDING');
            $table->timestampTz('issued_at');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('acked_at')->nullable();
            $table->timestampTz('timeout_at');
            $table->timestampsTz();

            $table->foreign('device_id')->references('device_id')->on('devices');
            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
