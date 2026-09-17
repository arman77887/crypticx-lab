<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->index();

            /*
             * register | authenticate
             */
            $table->string('purpose', 32);

            /*
             * Random WebAuthn challenge encoded as base64url.
             */
            $table->string('challenge', 255)->unique();

            /*
             * Serialized WebAuthn creation/request options.
             */
            $table->longText('options_json');

            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable()->index();

            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index([
                'user_id',
                'purpose',
                'expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_challenges');
    }
};
