<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Sesuaikan data existing jabatan di tabel sk_penugasan sesuai config('local.jabatan')
        $jabatanMap = [
            'ketua' => 1,
            'kepala sekolah' => 2,
            'kepala madrasah' => 2,
            'guru kelas' => 3,
            'guru mapel' => 4,
            'guru' => 4,
            'staf tata usaha' => 5,
            'tata usaha' => 5,
            'tu' => 5,
            'guru ekstrakurikuler' => 6,
            'ekstrakurikuler' => 6,
            'pendamping santri' => 7,
            'juru masak' => 8,
            'staf kebersihan' => 9,
            'kebersihan' => 9,
            'staf keamanan' => 10,
            'keamanan' => 10,
            'satpam' => 10,
            'staf it' => 11,
            'it' => 11,
            'staf lainnya' => 12,
            'staf' => 12,
            'jabatan lainnya' => 13,
        ];

        if (Schema::hasTable('sk_penugasan')) {
            $rows = DB::table('sk_penugasan')->get();
            foreach ($rows as $row) {
                $val = strtolower(trim((string) $row->jabatan));
                $newJabatan = null;

                if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 13) {
                    $newJabatan = (int) $val;
                } elseif (isset($jabatanMap[$val])) {
                    $newJabatan = $jabatanMap[$val];
                } else {
                    foreach ($jabatanMap as $k => $id) {
                        if (str_contains($val, $k)) {
                            $newJabatan = $id;
                            break;
                        }
                    }

                    if (!$newJabatan) {
                        if ($row->lembaga_id == 1) {
                            $newJabatan = 3; // Guru Kelas SDI
                        } elseif (in_array($row->lembaga_id, [2, 3])) {
                            $newJabatan = 4; // Guru Mapel SMPI / SMAI
                        } elseif ($row->lembaga_id == 99) {
                            $newJabatan = 12; // Staf Lainnya Yayasan
                        } else {
                            $newJabatan = 4; // Default Guru Mapel
                        }
                    }
                }

                DB::table('sk_penugasan')
                    ->where('id', $row->id)
                    ->update(['jabatan' => $newJabatan]);
            }
        }

        // 2. Ubah tipe kolom jabatan menjadi integer nullable
        Schema::table('sk_penugasan', function (Blueprint $table) {
            $table->integer('jabatan')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sk_penugasan', function (Blueprint $table) {
            $table->string('jabatan')->nullable()->change();
        });
    }
};
