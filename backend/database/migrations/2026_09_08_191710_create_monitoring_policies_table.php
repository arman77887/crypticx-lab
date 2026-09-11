<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->uuid('target_id');

            $table->boolean('enabled')->default(true)->index();

            $table->string('profile', 50)->default('standard');

            $table->unsignedInteger('interval_minutes')
                ->default(1440);

            $table->jsonb('configuration')->nullable();

            $table->timestampTz('last_scheduled_at')->nullable();
            $table->timestampTz('next_run_at')->nullable()->index();

            $table->uuid('last_assessment_id')->nullable();

            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('target_id')
                ->references('id')
                ->on('targets')
                ->cascadeOnDelete();

            $table->foreign('last_assessment_id')
                ->references('id')
                ->on('assessments')
                ->nullOnDelete();

            /*
             * V1 invariant:
             * one monitoring policy per target.
             */
            $table->unique('target_id');

            /*
             * Scheduler lookup:
             * enabled policies ordered/filtered by next_run_at.
             */
            $table->index([
                'enabled',
                'next_run_at',
            ]);

            $table->index([
                'user_id',
                'enabled',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_policies');
    }
};
