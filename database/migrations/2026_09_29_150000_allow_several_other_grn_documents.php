<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A goods receipt may also carry any number of "Other" files beside its LPO,
 * GRN and tax invoice, so a receipt can have several rows of one kind and the
 * one-per-kind unique index goes. The three named kinds stay one each: the
 * controller writes exactly one of each, once.
 *
 * Dropping a constraint only loosens what the release before this one may
 * write, so it runs on this schema unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_documents', function (Blueprint $table) {
            $table->dropUnique(['goods_receipt_note_id', 'kind']);
            $table->index(['goods_receipt_note_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('grn_documents', function (Blueprint $table) {
            $table->dropIndex(['goods_receipt_note_id', 'kind']);
            $table->unique(['goods_receipt_note_id', 'kind']);
        });
    }
};
