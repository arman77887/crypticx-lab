<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'monitoring_notification_preferences',
            function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->uuid('user_id')->unique();

                $table->boolean('email_enabled')
                    ->default(true);

                /*
                 * Event types the user wants delivered.
                 *
                 * Canonical V1 values:
                 * finding_new
                 * finding_reappeared
                 * finding_no_longer_detected
                 * finding_reopened
                 * risk_changed
                 */
                $table->jsonb('event_types');

                /*
                 * For risk_changed only.
                 * Notify when abs(delta_points) >= threshold.
                 */
                $table->unsignedSmallInteger(
                    'minimum_risk_delta'
                )->default(1);

                $table->timestampsTz();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'monitoring_notification_preferences'
        );
    }
};
