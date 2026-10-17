<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sk_penugasan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->integer('lembaga_id');
            $table->string('nomor_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tmt_tugas')->nullable();
            $table->date('tmt_selesai')->nullable();
            $table->integer('jabatan')->nullable();
            $table->boolean('is_induk')->default(false);
            $table->string('status')->default('1'); // 1 = Aktif, 0 = Non-Aktif / Selesai
            $table->string('file_sk')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // Migrasi data penugasan dari data pegawai yang sudah ada
        if (Schema::hasTable('pegawai')) {
            $pegawais = DB::table('pegawai')->get();
            foreach ($pegawais as $p) {
                if (!empty($p->lembaga_id)) {
                    $defaultJabatan = $p->lembaga_id == 1 ? 3 : ($p->lembaga_id == 99 ? 12 : 4);
                    DB::table('sk_penugasan')->insert([
                        'id' => (string) Str::uuid(),
                        'pegawai_id' => $p->id,
                        'lembaga_id' => $p->lembaga_id,
                        'nomor_sk' => null,
                        'tanggal_sk' => null,
                        'tmt_tugas' => $p->tmt,
                        'tmt_selesai' => null,
                        'jabatan' => $defaultJabatan,
                        'is_induk' => true,
                        'status' => '1',
                        'file_sk' => null,
                        'keterangan' => 'Penugasan awal (migrasi otomatis)',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sk_penugasan');
    }
};
