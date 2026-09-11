<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->index();

            // Random, opaque device identifier. Never store a raw fingerprint.
            $table->string('device_token_hash', 64)->unique();

            $table->string('name', 120)->nullable();

            $table->string('device_type', 50)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();

            $table->string('last_ip_address', 45)->nullable();

            $table->timestampTz('first_seen_at')->useCurrent();
            $table->timestampTz('last_seen_at')->useCurrent();

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();

            $table->boolean('is_trusted')->default(false)->index();
            $table->timestampTz('revoked_at')->nullable();

            $table->uuid('revoked_by')->nullable();

            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('revoked_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['user_id', 'is_trusted']);
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
