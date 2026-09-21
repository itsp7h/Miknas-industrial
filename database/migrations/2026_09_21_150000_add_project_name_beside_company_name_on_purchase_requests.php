<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A request names both: the company it is for, and the project within it.
 *
 * The MPR carried one field. It was the project; it briefly became the company
 * when the form changed. Neither on its own is the answer — a request belongs
 * to Miknas Industrial *and* to Forkoll — so the project comes back as its own
 * column beside the company.
 *
 * Rows that predate this hold a project name in `company_name`, because that
 * is what the single field meant when they were raised. They are moved across:
 * the value becomes the project, and the company is taken from that project's
 * own company. That is reading the fact they already carried, not inventing
 * one — "Forkoll" becomes project Forkoll, company Miknas Industrial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('project_name')->nullable()->after('company_name');
        });

        if (! Schema::hasTable('settings_projects')) {
            return;
        }

        $projects = DB::table('settings_projects')
            ->leftJoin('settings_companies', 'settings_companies.id', '=', 'settings_projects.company_id')
            ->select('settings_projects.name', 'settings_companies.name as company_name')
            ->get()
            ->keyBy('name');

        foreach (DB::table('purchase_requests')->whereNotNull('company_name')->get(['id', 'company_name']) as $row) {
            $project = $projects->get($row->company_name);

            if (! $project) {
                continue;
            }

            DB::table('purchase_requests')->where('id', $row->id)->update([
                'project_name' => $row->company_name,
                'company_name' => $project->company_name,
            ]);
        }
    }

    public function down(): void
    {
        // Put the project back where the single field held it, so the column
        // can be dropped without losing which project a request was for.
        DB::table('purchase_requests')->whereNotNull('project_name')->update([
            'company_name' => DB::raw('project_name'),
        ]);

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('project_name');
        });
    }
};
