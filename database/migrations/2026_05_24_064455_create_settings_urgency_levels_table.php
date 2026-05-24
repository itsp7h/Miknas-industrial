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
        Schema::create('settings_urgency_levels', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('emoji', 10)->default('📋');
            $table->string('color_bg', 20)->default('#f8fafc');
            $table->string('color_text', 20)->default('#475569');
            $table->string('subtitle', 100)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(99);
            $table->boolean('show_date_picker')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings_urgency_levels');
    }
};
