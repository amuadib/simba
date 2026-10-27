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
        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // Sambungkan akun admin yang sudah ada dengan pegawai yang bersesuaian jika cocok
        $adminUser = DB::table('users')->where('id', 1)->first();
        if ($adminUser) {
            $pegawai = DB::table('pegawai')
                ->where('nama', 'like', '%MUADIB%')
                ->whereNull('user_id')
                ->first();

            if ($pegawai) {
                DB::table('pegawai')->where('id', $pegawai->id)->update(['user_id' => $adminUser->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
