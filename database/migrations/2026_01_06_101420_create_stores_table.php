<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();
            $table->string('name', 150);

            // Level: PUSAT / CABANG / RETAIL (disimpan string agar simpel)
            $table->string('level', 20);

            // Hirarki: parent toko (self reference)
            $table->foreignId('parent_store_id')
                ->nullable()
                ->constrained('stores')
                ->nullOnDelete();

            $table->text('address')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Index untuk pencarian + listing
            $table->index(['level']);
            $table->index(['parent_store_id']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
