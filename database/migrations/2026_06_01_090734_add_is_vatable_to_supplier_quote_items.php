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
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->boolean('is_vatable')->default(false)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->dropColumn('is_vatable');
        });
    }
};
