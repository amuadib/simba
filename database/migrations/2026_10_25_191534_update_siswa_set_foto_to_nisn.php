<?php

use App\Models\Siswa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fotoExpression = match (DB::getDriverName()) {
            'sqlite', 'pgsql' => "'siswa/' || nisn || '.jpg'",
            default => "CONCAT('siswa/', nisn, '.jpg')",
        };

        Siswa::whereNotNull('nisn')
            ->whereNull('foto')
            ->update([
                'foto' => DB::raw($fotoExpression),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
