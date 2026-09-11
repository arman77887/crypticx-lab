<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_lifecycles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('target_id');
            $table->string('fingerprint', 64);

            $table->string('type', 100);
            $table->string('title', 255);
            $table->string('severity', 32);
            $table->string('confidence', 32);

            $table->string('status', 32)->default('open');

            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('reopened_at')->nullable();

            $table->unsignedInteger('occurrence_count')->default(1);

            $table->uuid('first_assessment_id');
            $table->uuid('last_assessment_id');
            $table->uuid('last_finding_id');

            $table->timestampsTz();

            $table->foreign('target_id')
                ->references('id')
                ->on('targets')
                ->cascadeOnDelete();

            $table->foreign('first_assessment_id')
                ->references('id')
                ->on('assessments')
                ->cascadeOnDelete();

            $table->foreign('last_assessment_id')
                ->references('id')
                ->on('assessments')
                ->cascadeOnDelete();

            $table->foreign('last_finding_id')
                ->references('id')
                ->on('findings')
                ->cascadeOnDelete();

            $table->unique(
                ['target_id', 'fingerprint'],
                'finding_lifecycles_target_fingerprint_unique'
            );

            $table->index(
                ['target_id', 'status'],
                'finding_lifecycles_target_status_index'
            );

            $table->index(
                ['target_id', 'last_seen_at'],
                'finding_lifecycles_target_last_seen_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_lifecycles');
    }
};
