<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->integer('item_id')->nullable()->change();
            $table->decimal('quantity', 12, 3)->nullable()->change();
            $table->string('unit_of_measure')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally left blank — reverting NOT NULL on legacy columns risks data loss
    }
};
