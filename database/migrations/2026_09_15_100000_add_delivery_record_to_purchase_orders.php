<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidence that the LPO actually reached the supplier.
     *
     * `status` already had a 'sent' value, and an order got it the moment it
     * was generated — while nothing emailed anybody. That is the same lie the
     * RFQ invitations used to tell, so the delivery record is kept apart from
     * the workflow status: `sent_at` is written only after the mailer returns,
     * and `sent_to` records the address it actually went to, which may not be
     * the one on the supplier today.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->string('sent_to')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'sent_to']);
        });
    }
};
