<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The people an MPR can be raised for — System → Requested By.
 *
 * The form's Requested By used to list every user account, which mixed logins
 * like "Admin User" in with the people who actually ask for materials, and
 * offered all of them for every company. A requester is a name, not an
 * account, and belongs to one or more companies; the form offers only the
 * chosen company's people.
 *
 * The list starts from the requests already raised: every name on an MPR,
 * mapped to each company it has been used for. Without that the dropdown would
 * be empty on the day this ships and nobody could raise a request until an
 * Admin had typed the list in.
 *
 * Only new tables: the release before this one never reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requesters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('company_requester', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('requesters')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('settings_companies')->cascadeOnDelete();
            $table->unique(['requester_id', 'company_id']);
            $table->index('company_id');
        });

        if (! Schema::hasTable('purchase_requests') || ! Schema::hasTable('settings_companies')) {
            return;
        }

        $companies = DB::table('settings_companies')->pluck('id', 'name');
        $now = now();

        $pairs = DB::table('purchase_requests')
            ->whereNotNull('requested_by_name')
            ->where('requested_by_name', '!=', '')
            ->select('requested_by_name', 'company_name')
            ->distinct()
            ->get();

        foreach ($pairs->groupBy(fn ($row) => trim($row->requested_by_name)) as $name => $rows) {
            $id = DB::table('requesters')->insertGetId([
                'name' => $name, 'created_at' => $now, 'updated_at' => $now,
            ]);

            // Older rows can carry a project name in company_name, or a
            // company since renamed; those simply map to nothing.
            $companyIds = $rows->pluck('company_name')
                ->map(fn ($company) => $companies[$company] ?? null)
                ->filter()->unique();

            foreach ($companyIds as $companyId) {
                DB::table('company_requester')->insert(['requester_id' => $id, 'company_id' => $companyId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_requester');
        Schema::dropIfExists('requesters');
    }
};
