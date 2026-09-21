<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who chose this supplier, and who sent them the request.
 *
 * Every other decision on a purchase request names the person who made it —
 * `requested_by`, `approved_by`, `rejected_by` on the request itself, and
 * `awarded_by` with a reason on the quote line. Selecting the suppliers had
 * nobody's name on it at all, though it is the step that decides who even gets
 * the chance to win the business. `sent_at` recorded when an invitation went
 * out and never by whom.
 *
 * Both are nullable: rows that predate this genuinely have no known actor, and
 * inventing one would be worse than admitting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table) {
            $table->foreignId('selected_by')->nullable()->after('channel')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->after('sent_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_by');
            $table->dropConstrainedForeignId('sent_by');
        });
    }
};
