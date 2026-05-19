<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'project_name')) {
                $table->string('project_name')->nullable()->after('date');
            }
            if (!Schema::hasColumn('purchase_requests', 'requested_by_name')) {
                $table->string('requested_by_name')->nullable()->after('department');
            }
            if (!Schema::hasColumn('purchase_requests', 'required_date_text')) {
                $table->string('required_date_text')->nullable()->after('requested_by_name');
            }
            if (!Schema::hasColumn('purchase_requests', 'location')) {
                $table->string('location')->nullable()->after('required_date_text');
            }
            if (!Schema::hasColumn('purchase_requests', 'verified_by_name')) {
                $table->string('verified_by_name')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('purchase_requests', 'project_name')      ? 'project_name'      : null,
                Schema::hasColumn('purchase_requests', 'requested_by_name') ? 'requested_by_name' : null,
                Schema::hasColumn('purchase_requests', 'required_date_text')? 'required_date_text': null,
                Schema::hasColumn('purchase_requests', 'location')          ? 'location'          : null,
                Schema::hasColumn('purchase_requests', 'verified_by_name')  ? 'verified_by_name'  : null,
            ]));
        });
    }
};
