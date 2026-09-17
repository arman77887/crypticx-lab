<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->index();

            /*
             * Raw WebAuthn credential ID encoded as base64url.
             * Hash is used for deterministic indexed lookup.
             */
            $table->text('credential_id');
            $table->string('credential_id_hash', 64)->unique();

            /*
             * Serialized PublicKeyCredentialSource.
             * Contains the public key only — never biometric data.
             */
            $table->longText('credential_source');

            $table->string('name', 120)->nullable();

            $table->json('transports')->nullable();

            $table->string('aaguid', 36)->nullable();

            $table->unsignedBigInteger('sign_count')->default(0);

            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable()->index();

            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index([
                'user_id',
                'revoked_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
