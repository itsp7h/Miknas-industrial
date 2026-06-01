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
            $table->string('supplier_description')->nullable()->after('description');
            $table->boolean('not_available')->default(false)->after('is_vatable');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->dropColumn(['supplier_description', 'not_available']);
        });
    }
};
