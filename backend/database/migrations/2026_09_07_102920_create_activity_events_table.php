<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->nullable()->index();

            $table->string('event_type', 100)->index();

            $table->string('ip_address', 45)->nullable()->index();
            $table->string('country_code', 10)->nullable()->index();
            $table->string('country_name', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();

            $table->string('device_type', 50)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['event_type', 'created_at']);
            $table->index(['country_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
