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
        Schema::create('jurnal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pembelajaran_id')->constrained('pembelajaran')->onDelete('cascade');
            $table->date('tanggal');
            $table->text('materi')->nullable();
            $table->timestamps();

            $table->unique(['pembelajaran_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal');
    }
};
