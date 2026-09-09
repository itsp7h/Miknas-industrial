<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quote_id')->constrained()->onDelete('cascade');
            $table->text('description');
            $table->string('unit', 50)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 12, 3);
            $table->decimal('total_price', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_items');
    }
};
