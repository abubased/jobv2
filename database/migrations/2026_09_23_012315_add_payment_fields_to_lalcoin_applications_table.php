<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lalcoin_applications', function (Blueprint $table) {
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_url')->nullable();
            $table->string('payment_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lalcoin_applications', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_reference',
                'payment_url',
                'payment_status',
            ]);
        });
    }
};
