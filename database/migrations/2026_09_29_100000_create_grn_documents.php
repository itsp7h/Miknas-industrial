<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paperwork a goods receipt is recorded against: the LPO, the supplier's
 * GRN (delivery note) and the tax invoice, one of each per receipt.
 *
 * The files themselves live on the private `local` disk
 * (storage/app/private/grn-documents), not in the database as the logos and
 * signatures do: these are scanned PDFs, not small images, and storage/ is
 * the shared directory every release links to, so a deploy does not lose them.
 *
 * A new table, so the release before this one runs on it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_note_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['goods_receipt_note_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_documents');
    }
};
