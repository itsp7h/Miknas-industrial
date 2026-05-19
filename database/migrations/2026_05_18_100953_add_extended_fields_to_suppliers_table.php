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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_code')->nullable()->after('id');
            $table->string('category')->nullable()->after('name');
            $table->string('secondary_email')->nullable()->after('email');
            $table->string('phone2')->nullable()->after('phone');
            $table->string('whatsapp')->nullable()->after('phone2');
            $table->string('website')->nullable()->after('address');
            $table->string('credit_terms', 10)->nullable()->after('tax_number');
            $table->integer('credit_days')->nullable()->after('credit_terms');
            $table->text('remarks')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_code', 'category', 'secondary_email',
                'phone2', 'whatsapp', 'website',
                'credit_terms', 'credit_days', 'remarks',
            ]);
        });
    }
};
