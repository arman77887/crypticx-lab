<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->uuid('target_id');
            $table->uuid('assessment_id');

            $table->string('title', 255);
            $table->string('status', 30)->default('ready');

            /*
             * Immutable report payload.
             *
             * These snapshots intentionally preserve the state that
             * existed when the report was generated. Future changes
             * to targets, findings, lifecycle state, or risk scoring
             * must not silently rewrite historical reports.
             */
            $table->jsonb('target_snapshot');
            $table->jsonb('assessment_snapshot');
            $table->jsonb('findings_snapshot');
            $table->jsonb('risk_snapshot');
            $table->jsonb('intelligence_snapshot')->nullable();
            $table->jsonb('metadata');

            $table->timestampTz('generated_at');

            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('target_id')
                ->references('id')
                ->on('targets')
                ->cascadeOnDelete();

            $table->foreign('assessment_id')
                ->references('id')
                ->on('assessments')
                ->cascadeOnDelete();

            $table->index(['user_id', 'generated_at']);
            $table->index(['target_id', 'generated_at']);
            $table->index(['assessment_id', 'generated_at']);
            $table->index(['status', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
