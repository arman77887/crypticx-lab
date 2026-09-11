<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_change_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->uuid('target_id');
            $table->uuid('assessment_id');
            $table->uuid('previous_assessment_id')->nullable();

            $table->string('event_type', 64);
            $table->string('fingerprint', 64)->nullable();

            /*
             * Immutable event payload.
             *
             * This stores the facts used when the event was generated.
             * It must not become a second finding-lifecycle state machine.
             */
            $table->jsonb('payload');

            $table->timestampTz('detected_at');
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

            $table->foreign('previous_assessment_id')
                ->references('id')
                ->on('assessments')
                ->nullOnDelete();

            /*
             * Idempotency:
             *
             * A completed assessment may be retried through application
             * orchestration, but the same logical change must only be
             * persisted once.
             *
             * PostgreSQL allows multiple NULL values in a UNIQUE
             * constraint, so non-finding events such as risk_changed use
             * a deterministic synthetic fingerprint in the service.
             */
            $table->unique(
                [
                    'assessment_id',
                    'event_type',
                    'fingerprint',
                ],
                'monitor_change_event_identity_unique'
            );

            $table->index(
                ['user_id', 'detected_at'],
                'monitor_change_events_user_time_index'
            );

            $table->index(
                ['target_id', 'detected_at'],
                'monitor_change_events_target_time_index'
            );

            $table->index(
                ['assessment_id', 'event_type'],
                'monitor_change_events_assessment_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_change_events');
    }
};
