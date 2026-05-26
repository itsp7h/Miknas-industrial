<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate table with company_id instead of project_id (SQLite-safe)
        Schema::create('settings_departments_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('settings_companies')->onDelete('cascade');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrate: map each department's project_id → that project's company_id
        DB::statement('
            INSERT INTO settings_departments_new (id, company_id, name, is_active, created_at, updated_at)
            SELECT d.id, p.company_id, d.name, d.is_active, d.created_at, d.updated_at
            FROM settings_departments d
            LEFT JOIN settings_projects p ON p.id = d.project_id
            WHERE p.company_id IS NOT NULL
        ');

        Schema::drop('settings_departments');
        Schema::rename('settings_departments_new', 'settings_departments');
    }

    public function down(): void
    {
        Schema::create('settings_departments_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('settings_projects')->onDelete('cascade');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::drop('settings_departments');
        Schema::rename('settings_departments_old', 'settings_departments');
    }
};
