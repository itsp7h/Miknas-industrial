<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a purchase request reached each stage, and who moved it there. Until
 * this only the current stage was stored, so the pipeline could not say when
 * anything happened or how long a step took.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_stage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The name at the time, so it survives the user being deleted, and
            // so a supplier answering on the public portal can be named too.
            $table->string('actor_name')->nullable();
            $table->timestamp('reached_at');
            $table->timestamps();

            $table->index(['purchase_request_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_stage_events');
    }
};
