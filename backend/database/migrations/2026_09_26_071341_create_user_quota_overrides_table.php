<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quota_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedInteger('targets_total')->nullable();
            $table->unsignedInteger('assessments_monthly')->nullable();
            $table->unsignedInteger('reports_monthly')->nullable();
            $table->unsignedInteger('monitoring_policies')->nullable();
            $table->unsignedInteger('concurrent_assessments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quota_overrides');
    }
};
