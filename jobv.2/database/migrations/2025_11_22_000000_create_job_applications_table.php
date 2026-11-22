<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('father_or_husband')->nullable();
            $table->string('mother')->nullable();
            $table->string('phone');
            $table->text('address');
            $table->string('education')->default('primary');
            $table->string('courier_help')->default('yes');
            $table->string('nearest_courier')->nullable();
            $table->string('payment_number')->nullable();
            $table->string('payment_amount')->nullable();
            $table->boolean('submitted')->default(false);
            $table->string('payment_status')->default('due');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
