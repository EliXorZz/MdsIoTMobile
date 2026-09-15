<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_results', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('command_id')->unique();
            $table->string('status');
            $table->boolean('ventilation')->nullable();
            $table->string('reason')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->timestampTz('reported_at')->nullable();
            $table->timestampsTz();

            $table->foreign('device_id')->references('device_id')->on('devices');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_results');
    }
};
