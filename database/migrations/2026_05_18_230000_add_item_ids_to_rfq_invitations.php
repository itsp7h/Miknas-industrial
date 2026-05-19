<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table) {
            // null = whole order; JSON array of item IDs = per-item assignment
            $table->text('item_ids')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table) {
            $table->dropColumn('item_ids');
        });
    }
};
