<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('assessment_id');
            $table->uuid('target_id');

            $table->string('type', 80)->index();
            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->string('severity', 20)->index();
            $table->string('confidence', 20)->index();

            $table->text('evidence')->nullable();
            $table->jsonb('evidence_data')->nullable();

            $table->text('remediation')->nullable();
            $table->string('status', 30)->default('open')->index();

            $table->timestampsTz();

            $table->foreign('assessment_id')
                ->references('id')
                ->on('assessments')
                ->cascadeOnDelete();

            $table->foreign('target_id')
                ->references('id')
                ->on('targets')
                ->cascadeOnDelete();

            $table->index(['assessment_id', 'severity']);
            $table->index(['target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
