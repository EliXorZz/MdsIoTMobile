<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;');
        DB::statement('CREATE EXTENSION IF NOT EXISTS timescaledb_toolkit CASCADE;');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS timescaledb CASCADE;');
        DB::statement('DROP EXTENSION IF EXISTS timescaledb_toolkit CASCADE;');
    }
};
