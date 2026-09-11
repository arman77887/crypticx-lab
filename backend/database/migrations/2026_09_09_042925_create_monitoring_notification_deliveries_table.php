<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'monitoring_notification_deliveries',
            function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->uuid('user_id');
                $table->uuid('change_event_id');

                $table->string('channel', 32);
                $table->string('status', 32)
                    ->default('pending');

                /*
                 * Recipient is snapshotted at delivery creation time.
                 * Future user email changes must not rewrite history.
                 */
                $table->string('recipient');

                /*
                 * Immutable notification content snapshot.
                 * No secret/provider credential belongs here.
                 */
                $table->jsonb('payload');

                $table->unsignedInteger('attempt_count')
                    ->default(0);

                $table->timestampTz('processing_at')
                    ->nullable();

                $table->timestampTz('sent_at')
                    ->nullable();

                $table->timestampTz('failed_at')
                    ->nullable();

                /*
                 * Bounded diagnostic classification only.
                 * Raw SMTP/provider responses are intentionally excluded.
                 */
                $table->string('failure_class', 255)
                    ->nullable();

                $table->timestampsTz();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign('change_event_id')
                    ->references('id')
                    ->on('monitoring_change_events')
                    ->cascadeOnDelete();

                $table->unique(
                    ['change_event_id', 'channel'],
                    'monitor_notification_event_channel_unique'
                );

                $table->index(
                    ['user_id', 'status', 'created_at'],
                    'monitor_notification_user_status_index'
                );

                $table->index(
                    ['status', 'created_at'],
                    'monitor_notification_status_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'monitoring_notification_deliveries'
        );
    }
};
