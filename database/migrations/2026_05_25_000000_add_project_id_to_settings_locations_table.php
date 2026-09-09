<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings_locations', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('name')
                ->constrained('settings_projects')
                ->nullOnDelete();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('settings_locations', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
