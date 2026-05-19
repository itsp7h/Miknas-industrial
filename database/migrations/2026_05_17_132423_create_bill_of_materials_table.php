<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('raw_material_id')->constrained('items')->onDelete('restrict');
            $table->decimal('quantity_required', 12, 2);
            $table->string('unit_of_measure')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_of_materials');
    }
};
