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
        Schema::create('siswa_tag', function (Blueprint $table) {
            $table->foreignUuid('siswa_id')
                ->constrained('siswa')
                ->cascadeOnDelete();
            $table->foreignUuid('tag_id')
                ->constrained('tag')
                ->restrictOnDelete();
            $table->unique(['siswa_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa_tag');
    }
};
