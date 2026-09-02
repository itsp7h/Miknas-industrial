<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A refusal needs the same audit record an approval gets. `approved_by`
     * and `approved_at` cannot be reused for it — a request approved and then
     * rejected still carries them, and writing them on a refusal would make
     * the MPR sheet print "Approved By" over it.
     *
     * All three are nullable and additive, so existing rows are untouched.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->foreignId('rejected_by')->nullable()->after('rejection_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejection_reason', 'rejected_at']);
        });
    }
};
