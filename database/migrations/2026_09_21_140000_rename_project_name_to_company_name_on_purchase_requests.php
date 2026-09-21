<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The MPR names a company, so the column says so.
 *
 * The form used to pick a project and the column was called `project_name` to
 * match. It now picks from the companies, which left the stored value and its
 * column name describing different things — and two places resolving an LPO's
 * company by looking the value up as a *project*, which quietly found nothing.
 *
 * Existing rows keep their value: four requests carry "Forkoll", a project.
 * They are history, they read correctly wherever they are shown, and rewriting
 * them into a company nobody chose would be inventing a fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->renameColumn('project_name', 'company_name');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->renameColumn('company_name', 'project_name');
        });
    }
};
