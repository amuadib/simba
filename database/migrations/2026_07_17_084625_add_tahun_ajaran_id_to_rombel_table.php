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
        Schema::table('rombel', function (Blueprint $table) {
            //UUID
            $table->string('tahun_ajaran_id')->after('tingkat')->nullable();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rombel', function (Blueprint $table) {
            //UUID
            $table->dropForeign(['tahun_ajaran_id']);
            $table->dropColumn('tahun_ajaran_id');
        });
    }
};
