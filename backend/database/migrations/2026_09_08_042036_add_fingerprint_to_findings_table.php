<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable()->after('type');

            $table->unique(
                ['assessment_id', 'fingerprint'],
                'findings_assessment_fingerprint_unique'
            );

            $table->index('fingerprint', 'findings_fingerprint_index');
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropUnique('findings_assessment_fingerprint_unique');
            $table->dropIndex('findings_fingerprint_index');
            $table->dropColumn('fingerprint');
        });
    }
};
