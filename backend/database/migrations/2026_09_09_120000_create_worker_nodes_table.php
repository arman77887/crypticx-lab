<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('worker_type', 50)->default('queue');
            $table->string('hostname', 255)->nullable();
            $table->unsignedBigInteger('pid')->nullable();

            $table->string('connection', 100)->nullable();
            $table->string('queue', 160)->nullable();

            $table->string('status', 30)->default('starting')->index();

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable()->index();
            $table->timestampTz('stopped_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            $table->index(
                ['worker_type', 'status'],
                'worker_nodes_type_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_nodes');
    }
};
