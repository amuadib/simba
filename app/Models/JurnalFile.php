<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JurnalFile extends Model
{
    use HasUuids;

    protected $table = 'jurnal_files';

    protected $fillable = [
        'jurnal_id',
        'nama_file',
        'path',
        'mime_type',
        'ukuran',
    ];

    public function jurnal()
    {
        return $this->belongsTo(Jurnal::class);
    }

    /**
     * Apakah file dapat di-preview di browser (gambar/PDF/video).
     */
    public function isPreviewable(): bool
    {
        $previewable = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf',
            'video/mp4', 'video/webm', 'video/ogg',
            'audio/mpeg', 'audio/ogg', 'audio/wav',
        ];
        return in_array($this->mime_type, $previewable);
    }

    /**
     * Icon Bootstrap sesuai tipe file.
     */
    public function iconClass(): string
    {
        if (str_starts_with($this->mime_type ?? '', 'image/')) return 'bi-file-earmark-image text-pink-500';
        if ($this->mime_type === 'application/pdf') return 'bi-file-earmark-pdf text-red-500';
        if (str_starts_with($this->mime_type ?? '', 'video/')) return 'bi-file-earmark-play text-purple-500';
        if (str_starts_with($this->mime_type ?? '', 'audio/')) return 'bi-file-earmark-music text-indigo-500';
        if (in_array($this->mime_type, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) return 'bi-file-earmark-word text-blue-500';
        if (in_array($this->mime_type, ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])) return 'bi-file-earmark-excel text-green-500';
        if (in_array($this->mime_type, ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'])) return 'bi-file-earmark-ppt text-orange-500';
        return 'bi-file-earmark text-slate-500';
    }

    /**
     * Ukuran file dalam format yang mudah dibaca.
     */
    public function ukuranFormatted(): string
    {
        if (!$this->ukuran) return '-';
        if ($this->ukuran < 1024) return $this->ukuran . ' B';
        if ($this->ukuran < 1024 * 1024) return round($this->ukuran / 1024, 1) . ' KB';
        return round($this->ukuran / (1024 * 1024), 1) . ' MB';
    }
}

