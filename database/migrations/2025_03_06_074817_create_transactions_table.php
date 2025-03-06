<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('fraud_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('issuer')->nullable();
            $table->string('acquirer')->nullable();
            $table->string('payment_code')->nullable();
            $table->string('va_number')->nullable();
            $table->timestamp('expiry_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
