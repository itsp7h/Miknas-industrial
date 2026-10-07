<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A supplier may attach their own quotation — their letterhead, their terms —
 * beside the figures they type into the portal. Optional, and one file. It
 * lives on the private `local` disk under quote-documents/{quote id}, like a
 * GRN's paperwork, so only staff who may see the quotes can open it.
 *
 * Nullable: every quote before this has none, and the release before this one
 * runs on it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('notes');
            $table->string('document_name')->nullable()->after('document_path');
            $table->unsignedInteger('document_size')->nullable()->after('document_name');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_name', 'document_size']);
        });
    }
};
