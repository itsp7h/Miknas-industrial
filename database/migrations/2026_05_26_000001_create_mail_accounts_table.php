<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('label', 150);
            $table->enum('type', ['azure', 'smtp']);
            $table->string('from_address', 255);
            $table->string('from_name', 150)->nullable();
            $table->text('config');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
