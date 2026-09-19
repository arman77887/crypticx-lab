<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'monitoring_notification_preferences',
            function (Blueprint $table): void {
                $table->boolean('daily_digest_enabled')
                    ->default(true);

                $table->string('daily_digest_timezone', 64)
                    ->default('UTC');

                $table->unsignedTinyInteger('daily_digest_hour')
                    ->default(8);
            }
        );

        Schema::create(
            'monitoring_daily_digest_deliveries',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('user_id');
                $table->date('digest_date');

                $table->string('timezone', 64);
                $table->string('recipient');

                $table->string('status', 32)
                    ->default('pending');

                $table->json('payload');

                $table->unsignedInteger('attempt_count')
                    ->default(0);

                $table->timestamp('processing_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();

                $table->string('failure_class', 255)
                    ->nullable();

                $table->timestamps();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                /*
                 * Exactly one digest delivery per user/local date.
                 * This is the database-level idempotency boundary.
                 */
                $table->unique(
                    ['user_id', 'digest_date'],
                    'monitoring_daily_digest_user_date_unique'
                );

                $table->index(
                    ['status', 'created_at'],
                    'monitoring_daily_digest_status_created_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'monitoring_daily_digest_deliveries'
        );

        Schema::table(
            'monitoring_notification_preferences',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'daily_digest_enabled',
                    'daily_digest_timezone',
                    'daily_digest_hour',
                ]);
            }
        );
    }
};
