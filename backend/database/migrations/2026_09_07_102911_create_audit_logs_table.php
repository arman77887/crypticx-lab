<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->nullable()->index();

            $table->string('action', 150)->index();
            $table->string('category', 80)->index();

            $table->string('method', 10)->nullable();
            $table->string('route', 255)->nullable();

            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();

            $table->string('resource_type', 150)->nullable();
            $table->string('resource_id', 100)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['category', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
