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
        Schema::create('nilai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jurnal_id')->constrained('jurnal')->cascadeOnDelete();
            $table->foreignUuid('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->integer('jenis_nilai_id');
            $table->decimal('nilai', 3, 2);
            $table->timestamps();

            $table->unique(['jurnal_id', 'siswa_id', 'jenis_nilai_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai');
    }
};
