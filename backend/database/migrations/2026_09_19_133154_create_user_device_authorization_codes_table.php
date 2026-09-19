<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_authorization_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // OTP is never stored in plaintext.
            $table->string('code_hash', 255);
            $table->string('request_token_hash', 64)->unique();

            $table->string('device_type', 32)->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('platform', 64)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index([
                'user_id',
                'expires_at',
            ]);

            $table->index([
                'user_id',
                'used_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_device_authorization_codes'
        );
    }
};
