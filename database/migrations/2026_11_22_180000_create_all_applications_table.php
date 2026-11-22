<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('all_applications', function (Blueprint $table) {
            $table->id();
            $table->string('form_slug', 64);
            $table->string('form_name');
            $table->string('name');
            $table->string('phone', 32);
            $table->text('address');
            $table->json('form_data')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->text('payment_url')->nullable();
            $table->string('payment_status', 32)->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->string('merchant_name')->nullable();
            $table->boolean('submitted')->default(true);
            $table->timestamps();

            $table->index('form_slug');
            $table->index('phone');
            $table->index('payment_reference');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('all_applications');
    }
};
