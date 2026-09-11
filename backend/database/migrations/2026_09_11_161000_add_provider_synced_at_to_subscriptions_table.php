<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            /*
             * Paddle explicitly allows webhook events to arrive
             * out of order. Store the provider event timestamp so
             * an older event cannot overwrite newer subscription state.
             */
            $table->timestampTz('provider_synced_at')
                ->nullable()
                ->after('canceled_at');

            $table->index(
                ['provider', 'provider_synced_at'],
                'subscriptions_provider_synced_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(
                'subscriptions_provider_synced_index'
            );

            $table->dropColumn(
                'provider_synced_at'
            );
        });
    }
};
