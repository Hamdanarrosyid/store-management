<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->string('sku', 80);
            $table->string('name', 200);
            $table->text('description')->nullable();

            // PostgreSQL akan map ke numeric(14,2)
            $table->decimal('price', 14, 2);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // SKU unik per toko
            $table->unique(['store_id', 'sku']);

            // Index untuk pencarian & listing
            $table->index(['store_id', 'name']);
            $table->index(['store_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
