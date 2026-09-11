<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('plan_code', 50);
            $table->string('status', 32)->default('active');

            /*
             * Provider fields remain nullable until a real payment
             * integration is configured in Phase 4E.
             */
            $table->string('provider', 50)->nullable();
            $table->string('provider_customer_id', 191)->nullable();
            $table->string('provider_subscription_id', 191)->nullable();

            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();

            $table->boolean('cancel_at_period_end')
                ->default(false);

            $table->timestampTz('canceled_at')->nullable();
            $table->timestampsTz();

            $table->index(
                ['user_id', 'status'],
                'subscriptions_user_status_index'
            );

            $table->index(
                ['user_id', 'current_period_end'],
                'subscriptions_user_period_index'
            );

            $table->unique(
                ['provider', 'provider_subscription_id'],
                'subscriptions_provider_subscription_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
