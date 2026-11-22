<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Run: php artisan migrate on the production server after deploying.

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lalcoin_applications', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('coin_year');
            // TODO (deferred, not part of this change): payment_number / payment_amount
            // may still be missing a migration on this table — confirm separately before
            // relying on them; do not add that migration here.
        });
    }

    public function down(): void
    {
        Schema::table('lalcoin_applications', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
