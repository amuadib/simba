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
        Schema::create('surats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lembaga_id');
            $table->string('nomor_surat');
            $table->integer('nomor_urut');
            $table->string('kode_surat', 20);
            $table->string('singkatan_lembaga', 30);
            $table->string('bulan_romawi', 10);
            $table->integer('tahun');
            $table->date('tanggal_surat');
            $table->string('perihal');
            $table->string('tujuan')->nullable();
            $table->string('jenis_pembuatan', 20)->default('template'); // 'template' | 'upload'
            $table->foreignId('surat_template_id')->nullable()->constrained('surat_templates')->nullOnDelete();
            $table->longText('konten')->nullable();
            $table->string('file_path')->nullable();
            $table->string('target_type', 30)->nullable(); // 'siswa', 'pegawai', 'umum'
            $table->string('target_id', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lembaga_id', 'tahun', 'nomor_urut']);
            $table->index('nomor_surat');
            $table->index('kode_surat');
            $table->index('tanggal_surat');
            $table->index('jenis_pembuatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surats');
    }
};
