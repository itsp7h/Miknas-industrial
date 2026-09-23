<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a company calls its material request, in the middle of the number.
 *
 * Most of them file an MPR — MI-MPR-26-0001. Matana steel Factory's own
 * paperwork calls it an MRF, and a number is only useful if it reads the way
 * the document on the desk does, so the token is the company's to set.
 *
 * Null means MPR. The column exists so the exception does not have to be
 * spelled out in the generator, and Matana is only the first row to use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->string('mpr_code', 8)->nullable()->after('lpo_code');
        });

        // Matched on the name because that is what identifies the company
        // across staging and production, where the ids differ. A site without
        // this company is simply left alone.
        DB::table('settings_companies')
            ->whereRaw('lower(name) like ?', ['%matana%'])
            ->update(['mpr_code' => 'MRF']);
    }

    public function down(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->dropColumn('mpr_code');
        });
    }
};
