<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_invitation_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->timestamp('submitted_at');
            $table->integer('lead_time_days')->nullable();
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 12, 3)->default(0);
            $table->boolean('is_awarded')->default(false);
            $table->text('award_reason')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quotes');
    }
};
