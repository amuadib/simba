<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('siswa_lembaga') || !Schema::hasTable('siswa')) {
            return;
        }

        // Ambil ID siswa yang sudah terdaftar di lembaga 4 agar tidak terjadi duplikasi
        $existingLembaga4SiswaIds = DB::table('siswa_lembaga')
            ->where('lembaga_id', 4)
            ->pluck('siswa_id')
            ->all();
        $existingLookup = array_flip($existingLembaga4SiswaIds);

        // Ambil semua siswa yang terafiliasi dengan lembaga 2 (SMPI Miftahul Ulum)
        $siswas = DB::table('siswa')
            ->where(function ($q) {
                $q->where('lembaga_id', 2)
                  ->orWhereExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('siswa_lembaga')
                          ->whereColumn('siswa_lembaga.siswa_id', 'siswa.id')
                          ->where('siswa_lembaga.lembaga_id', 2);
                  });
            })
            ->get();

        $now = now();
        $batch = [];

        foreach ($siswas as $s) {
            // Lewati jika sudah terdaftar di lembaga 4
            if (isset($existingLookup[$s->id])) {
                continue;
            }

            $batch[] = [
                'id' => (string) Str::uuid(),
                'siswa_id' => $s->id,
                'lembaga_id' => 4,
                'rombel_id' => null,
                'nis_lokal' => $s->nis ?? null,
                'is_induk' => false,
                'status' => (int) ($s->status ?? 1),
                'tmt_masuk' => null,
                'tmt_keluar' => null,
                'keterangan' => 'Santri Pondok Pesantren (migrasi otomatis dari SMPI)',
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('siswa_lembaga')) {
            DB::table('siswa_lembaga')
                ->where('lembaga_id', 4)
                ->where('keterangan', 'Santri Pondok Pesantren (migrasi otomatis dari SMPI)')
                ->delete();
        }
    }
};
