<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('lalcoin_applications', 'lalcoin_applications_phone_unique')) {
            return;
        }

        Schema::table('lalcoin_applications', function (Blueprint $table) {
            $table->dropUnique('lalcoin_applications_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lalcoin_applications', function (Blueprint $table) {
            $table->unique('phone');
        });
    }
};
