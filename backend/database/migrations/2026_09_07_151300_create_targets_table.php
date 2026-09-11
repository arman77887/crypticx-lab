<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->string('name', 160);
            $table->text('url');
            $table->string('hostname', 255)->index();
            $table->string('scheme', 10);
            $table->unsignedSmallInteger('port')->nullable();

            $table->boolean('authorization_confirmed')->default(false);
            $table->timestampTz('authorization_confirmed_at')->nullable();
            $table->string('authorization_method', 80)->nullable();

            $table->string('status', 30)->default('active')->index();

            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
