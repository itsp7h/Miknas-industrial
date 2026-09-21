<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each company's own letters in its LPO numbers.
 *
 * An LPO reads ST-LPO-26-0001: the company's code, the document, the year and
 * a sequence that starts again each year per company. Purchase orders were
 * numbered PO-00001 across the whole system, which says nothing about who
 * issued it.
 *
 * The code is seeded from the company's initials — Miknas Industrial -> MI,
 * Steel Tech -> ST, Matana Steel -> MS — because that is what the codes are,
 * and it is editable in Settings for the ones where initials are not wanted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->string('lpo_code', 8)->nullable()->after('name');
        });

        foreach (DB::table('settings_companies')->get(['id', 'name']) as $company) {
            DB::table('settings_companies')->where('id', $company->id)->update([
                'lpo_code' => $this->initials($company->name),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('settings_companies', function (Blueprint $table) {
            $table->dropColumn('lpo_code');
        });
    }

    /** First letter of each word, up to four: "Miknas Industrial" -> "MI". */
    private function initials(string $name): string
    {
        $letters = collect(preg_split('/[^A-Za-z]+/', $name, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($word) => strtoupper($word[0]))
            ->take(4)
            ->implode('');

        // A name with no letters in it at all still needs something.
        return $letters !== '' ? $letters : 'CO';
    }
};
