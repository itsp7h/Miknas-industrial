<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->foreignId('purchase_request_item_id')->nullable()->after('supplier_quote_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_request_item_id');
        });
    }
};
