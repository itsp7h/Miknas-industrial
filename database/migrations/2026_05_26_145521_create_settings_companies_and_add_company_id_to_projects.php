<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('settings_projects', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')
                ->constrained('settings_companies')->nullOnDelete();
        });

        // Assign all existing projects to a "General" company
        if (DB::table('settings_projects')->exists()) {
            $id = DB::table('settings_companies')->insertGetId(['name' => 'General', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('settings_projects')->update(['company_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('settings_projects', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
        Schema::dropIfExists('settings_companies');
    }
};
