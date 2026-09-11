<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->uuid('target_id');

            $table->string('profile', 50)->default('standard');
            $table->string('status', 30)->default('queued')->index();

            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->string('worker_id', 160)->nullable();
            $table->unsignedInteger('progress')->default(0);

            $table->jsonb('configuration')->nullable();
            $table->jsonb('execution_metadata')->nullable();

            $table->text('error_message')->nullable();

            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('target_id')
                ->references('id')
                ->on('targets')
                ->cascadeOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['target_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
