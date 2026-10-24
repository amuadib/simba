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
        Schema::dropIfExists('siswa_lembaga');
        Schema::create('siswa_lembaga', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->integer('lembaga_id');
            $table->foreignUuid('rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
            $table->string('nis_lokal')->nullable();
            $table->boolean('is_induk')->default(false);
            $table->integer('status')->default(1); // 1: Aktif, 2: Lulus, 3: Mutasi, 4: Non-Aktif, 5: Almarhum, 6: Keluar
            $table->date('tmt_masuk')->nullable();
            $table->date('tmt_keluar')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // Migrasi data awal dari tabel siswa yang sudah ada
        if (Schema::hasTable('siswa')) {
            $validRombels = array_flip(DB::table('rombel')->pluck('id')->all());
            $siswas = DB::table('siswa')->get();
            $now = now();
            $batch = [];

            foreach ($siswas as $s) {
                $lembagaId = !empty($s->lembaga_id) ? (int) $s->lembaga_id : 99;
                $rombelId = (!empty($s->rombel_id) && isset($validRombels[$s->rombel_id])) ? $s->rombel_id : null;

                $batch[] = [
                    'id' => (string) Str::uuid(),
                    'siswa_id' => $s->id,
                    'lembaga_id' => $lembagaId,
                    'rombel_id' => $rombelId,
                    'nis_lokal' => $s->nis ?? null,
                    'is_induk' => true,
                    'status' => (int) ($s->status ?? 1),
                    'tmt_masuk' => null,
                    'tmt_keluar' => null,
                    'keterangan' => 'Keanggotaan awal (migrasi otomatis)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 100) {
                    DB::table('siswa_lembaga')->insert($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('siswa_lembaga')->insert($batch);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa_lembaga');
    }
};
