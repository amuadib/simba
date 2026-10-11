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
        if (!Schema::hasColumn('settings', 'payload')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->json('payload')->nullable()->after('key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('settings', 'payload')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('payload');
            });
        }
    }
};
