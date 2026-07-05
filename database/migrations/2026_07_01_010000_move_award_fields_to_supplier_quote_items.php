<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->boolean('is_awarded')->default(false)->after('not_available');
            $table->text('award_reason')->nullable()->after('is_awarded');
            $table->timestamp('awarded_at')->nullable()->after('award_reason');
            $table->foreignId('awarded_by')->nullable()->after('awarded_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('awarded_by');
            $table->dropColumn(['is_awarded', 'award_reason', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->boolean('is_awarded')->default(false);
            $table->text('award_reason')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('supplier_quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('awarded_by');
            $table->dropColumn(['is_awarded', 'award_reason', 'awarded_at']);
        });
    }
};
