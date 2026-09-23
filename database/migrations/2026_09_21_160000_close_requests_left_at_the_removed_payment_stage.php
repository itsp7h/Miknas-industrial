<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Payment is no longer a stage on the purchase pipeline.
 *
 * Requests sitting at it have had everything received — payment was the step
 * after receiving — so they are finished as far as the pipeline is concerned
 * and move to `complete`. Leaving them at a stage the machine no longer knows
 * would strand them: `setStageIfNotPast` compares positions in the stage list,
 * and a stage that is not in it has no position.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_requests')->where('stage', 'payment')->update(['stage' => 'complete']);
    }

    public function down(): void
    {
        // There is no telling afterwards which completed requests had only
        // reached payment, so this does not try to guess. Nothing is lost:
        // `complete` is where they were heading.
    }
};
