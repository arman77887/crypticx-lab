<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'personal_access_tokens',
            function (Blueprint $table): void {
                $table->uuid('trusted_device_id')
                    ->nullable()
                    ->after('tokenable_id');

                $table->foreign('trusted_device_id')
                    ->references('id')
                    ->on('trusted_devices')
                    ->nullOnDelete();

                $table->index(
                    'trusted_device_id',
                    'pat_trusted_device_id_index',
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'personal_access_tokens',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'trusted_device_id',
                ]);

                $table->dropIndex(
                    'pat_trusted_device_id_index'
                );

                $table->dropColumn(
                    'trusted_device_id'
                );
            }
        );
    }
};
