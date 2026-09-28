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
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('nik')->nullable()->after('nama');
            $table->string('nis')->nullable()->after('nik');
            $table->string('no_akte')->nullable()->after('nis');
            $table->string('no_kk')->nullable()->after('no_akte');
            $table->string('tempat_lahir')->nullable()->after('no_kk');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->integer('lembaga_id')->nullable()->after('tanggal_lahir')->default(99);
            $table->string('alamat')->nullable()->after('lembaga_id');
            $table->string('telepon')->nullable()->after('alamat');
            $table->string('ayah')->nullable()->after('telepon');
            $table->string('ibu')->nullable()->after('ayah');
            $table->string('foto')->nullable()->after('ibu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn(['nik', 'nis', 'no_akte', 'no_kk', 'tempat_lahir', 'tanggal_lahir', 'lembaga_id', 'alamat', 'telepon', 'ayah', 'ibu', 'foto']);
        });
    }
};
