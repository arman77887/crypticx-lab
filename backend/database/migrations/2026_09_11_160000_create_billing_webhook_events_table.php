<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('provider', 50);
            $table->string('event_id', 191);
            $table->string('notification_id', 191)->nullable();
            $table->string('event_type', 100);

            $table->timestampTz('occurred_at')->nullable();
            $table->timestampTz('processed_at')->nullable();

            $table->string('processing_status', 32)
                ->default('received');

            $table->text('failure_reason')->nullable();

            $table->timestampsTz();

            $table->unique(
                ['provider', 'event_id'],
                'billing_webhook_provider_event_unique'
            );

            $table->index(
                ['provider', 'event_type'],
                'billing_webhook_provider_type_index'
            );

            $table->index(
                ['processing_status', 'created_at'],
                'billing_webhook_status_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
