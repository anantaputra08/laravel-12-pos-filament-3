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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique();
            $table->string('name');
            $table->foreignId('type_id')->constrained('product_types')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->boolean('is_available')->default(true);
            $table->boolean('is_stock')->default(true);
            $table->decimal('base_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->integer('stock');
            $table->integer('min_stock')->default(0);
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('base_unit');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
