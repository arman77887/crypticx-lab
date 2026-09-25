<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webauthn_credentials', function (Blueprint $table) {
            $table->string('rp_id')
                ->nullable()
                ->after('user_id');

            $table->index(
                ['user_id', 'rp_id'],
                'webauthn_credentials_user_rp_index'
            );
        });

        Schema::table('webauthn_challenges', function (Blueprint $table) {
            $table->string('rp_id')
                ->nullable()
                ->after('purpose');

            $table->string('origin')
                ->nullable()
                ->after('rp_id');
        });

        // Every credential that existed before this migration was
        // registered against the original DuckDNS relying party.
        DB::table('webauthn_credentials')
            ->whereNull('rp_id')
            ->update([
                'rp_id' => 'crypticxlab.duckdns.org',
            ]);
    }

    public function down(): void
    {
        Schema::table('webauthn_challenges', function (Blueprint $table) {
            $table->dropColumn(['rp_id', 'origin']);
        });

        Schema::table('webauthn_credentials', function (Blueprint $table) {
            $table->dropIndex(
                'webauthn_credentials_user_rp_index'
            );

            $table->dropColumn('rp_id');
        });
    }
};
