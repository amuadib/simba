<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jurnal_id')->constrained('jurnal')->onDelete('cascade');
            $table->string('nama_file');       // original filename
            $table->string('path');            // storage path (public disk)
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('ukuran')->nullable(); // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_files');
    }
};
