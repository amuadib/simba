<?php

namespace Database\Seeders;

use App\Models\SuratTemplate;
use Illuminate\Database\Seeder;

class SuratTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'nama'       => 'Surat Keterangan Siswa Aktif',
                'kode_surat' => 'SKet',
                'lembaga_id' => null, // Berlaku untuk semua lembaga
                'deskripsi'  => 'Template surat keterangan bahwa siswa berstatus aktif bersekolah',
                'konten'     => '<p style="text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 2px;"><u>SURAT KETERANGAN AKTIF BELAJAR</u></p>
<p style="text-align: center; font-size: 11pt; margin-top: 0; margin-bottom: 25px;">Nomor: {nomor_surat}</p>

<p>Yang bertanda tangan di bawah ini:</p>
<table style="width: 100%; margin-left: 20px; margin-bottom: 15px; border-collapse: collapse;">
    <tr><td style="width: 25%; padding: 4px 0;">Nama</td><td style="width: 3%;">:</td><td style="font-weight: bold;">{kepala_lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Jabatan</td><td>:</td><td>Kepala {lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Unit Kerja</td><td>:</td><td>{lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Alamat Lembaga</td><td>:</td><td>{alamat_lembaga}</td></tr>
</table>

<p>Dengan ini menerangkan dengan sebenarnya bahwa:</p>
<table style="width: 100%; margin-left: 20px; margin-bottom: 15px; border-collapse: collapse;">
    <tr><td style="width: 25%; padding: 4px 0;">Nama Lengkap</td><td style="width: 3%;">:</td><td style="font-weight: bold;">{nama}</td></tr>
    <tr><td style="padding: 4px 0;">NISN / NIS</td><td>:</td><td>{nisn} / {nis}</td></tr>
    <tr><td style="padding: 4px 0;">NIK</td><td>:</td><td>{nik}</td></tr>
    <tr><td style="padding: 4px 0;">Tempat, Tgl Lahir</td><td>:</td><td>{ttl}</td></tr>
    <tr><td style="padding: 4px 0;">Kelas / Rombel</td><td>:</td><td>{rombel}</td></tr>
    <tr><td style="padding: 4px 0;">Alamat</td><td>:</td><td>{alamat}</td></tr>
</table>

<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Yang bersangkutan adalah benar-benar siswa/siswi aktif yang terdaftar dan sedang menempuh pendidikan pada <strong>{lembaga}</strong> tahun ajaran saat ini dan berkelakuan baik dalam menjalankan kegiatan belajar mengajar.
</p>
<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Demikian surat keterangan ini kami buat dengan sebenarnya dan agar dapat dipergunakan sebagaimana mestinya.
</p>',
                'is_active'  => true,
            ],
            [
                'nama'       => 'Surat Tugas Kedinasan Pegawai',
                'kode_surat' => 'STgs',
                'lembaga_id' => null,
                'deskripsi'  => 'Template penugasan kedinasan untuk guru atau staf pegawai',
                'konten'     => '<p style="text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 2px;"><u>SURAT TUGAS</u></p>
<p style="text-align: center; font-size: 11pt; margin-top: 0; margin-bottom: 25px;">Nomor: {nomor_surat}</p>

<p>Yang bertanda tangan di bawah ini:</p>
<table style="width: 100%; margin-left: 20px; margin-bottom: 15px; border-collapse: collapse;">
    <tr><td style="width: 25%; padding: 4px 0;">Nama</td><td style="width: 3%;">:</td><td style="font-weight: bold;">{kepala_lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Jabatan</td><td>:</td><td>Kepala {lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Unit Kerja</td><td>:</td><td>{lembaga}</td></tr>
</table>

<p>Memberikan tugas kedinasan kepada:</p>
<table style="width: 100%; margin-left: 20px; margin-bottom: 15px; border-collapse: collapse;">
    <tr><td style="width: 25%; padding: 4px 0;">Nama Lengkap</td><td style="width: 3%;">:</td><td style="font-weight: bold;">{nama}</td></tr>
    <tr><td style="padding: 4px 0;">NIY / NUPTK</td><td>:</td><td>{niy} / {nuptk}</td></tr>
    <tr><td style="padding: 4px 0;">Jabatan</td><td>:</td><td>{jabatan}</td></tr>
    <tr><td style="padding: 4px 0;">Alamat</td><td>:</td><td>{alamat}</td></tr>
</table>

<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Untuk melaksanakan tugas perihal: <strong>{perihal}</strong> dengan penuh tanggung jawab dan melaporkan hasil pelaksanaan tugas setelah kegiatan dinas selesai.
</p>
<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Demikian surat tugas ini diberikan kepada yang bersangkutan untuk dilaksanakan dengan sebaik-baiknya.
</p>',
                'is_active'  => true,
            ],
            [
                'nama'       => 'Surat Keterangan Bekerja Pegawai',
                'kode_surat' => 'SKet',
                'lembaga_id' => null,
                'deskripsi'  => 'Template surat keterangan aktif bekerja untuk pendidik/tenaga kependidikan',
                'konten'     => '<p style="text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 2px;"><u>SURAT KETERANGAN BEKERJA</u></p>
<p style="text-align: center; font-size: 11pt; margin-top: 0; margin-bottom: 25px;">Nomor: {nomor_surat}</p>

<p>Yang bertanda tangan di bawah ini:</p>
<table style="width: 100%; margin-left: 20px; margin-bottom: 15px; border-collapse: collapse;">
    <tr><td style="width: 25%; padding: 4px 0;">Nama</td><td style="width: 3%;">:</td><td style="font-weight: bold;">{kepala_lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Jabatan</td><td>:</td><td>Kepala {lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Unit Kerja</td><td>:</td><td>{lembaga}</td></tr>
</table>

<p>Menerangkan dengan sebenarnya bahwa:</p>
<table style="width: 100%; margin-left: 20px; margin-bottom: 15px; border-collapse: collapse;">
    <tr><td style="width: 25%; padding: 4px 0;">Nama</td><td style="width: 3%;">:</td><td style="font-weight: bold;">{nama}</td></tr>
    <tr><td style="padding: 4px 0;">NIY / NUPTK</td><td>:</td><td>{niy} / {nuptk}</td></tr>
    <tr><td style="padding: 4px 0;">NIK</td><td>:</td><td>{nik}</td></tr>
    <tr><td style="padding: 4px 0;">Tempat, Tgl Lahir</td><td>:</td><td>{ttl}</td></tr>
    <tr><td style="padding: 4px 0;">Jabatan</td><td>:</td><td>{jabatan}</td></tr>
    <tr><td style="padding: 4px 0;">Alamat</td><td>:</td><td>{alamat}</td></tr>
</table>

<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Adalah benar-benar Tenaga Pendidik / Kependidikan yang aktif bekerja dan mengabdi di <strong>{lembaga}</strong> terhitung sejak SK penugasan sampai dengan saat ini serta memiliki dedikasi dan kinerja yang baik.
</p>
<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Demikian surat keterangan ini kami buat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
</p>',
                'is_active'  => true,
            ],
            [
                'nama'       => 'Surat Undangan Kegiatan / Wali Murid',
                'kode_surat' => 'U',
                'lembaga_id' => null,
                'deskripsi'  => 'Template surat undangan resmi pertemuan/kegiatan sekolah',
                'konten'     => '<p style="text-align: right; margin-bottom: 20px;">{tanggal_surat}</p>
<table style="width: 100%; margin-bottom: 20px;">
    <tr><td style="width: 15%;">Nomor</td><td style="width: 3%;">:</td><td>{nomor_surat}</td></tr>
    <tr><td>Lampiran</td><td>:</td><td>-</td></tr>
    <tr><td>Perihal</td><td>:</td><td><strong>{perihal}</strong></td></tr>
</table>

<p>Kepada Yth.<br><strong>{tujuan}</strong><br>di Tempat</p>

<p style="margin-top: 15px;"><em>Assalamu\'alaikum Warahmatullahi Wabarakatuh,</em></p>
<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Puji syukur senantiasa kita panjatkan ke hadirat Allah SWT atas segala limpahan rahmat dan hidayah-Nya. Shalawat dan salam semoga tercurah kepada Nabi Muhammad SAW beserta keluarga dan para sahabatnya.
</p>
<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Sehubungan dengan kegiatan di lingkungan <strong>{lembaga}</strong>, kami mengharapkan kehadiran Bapak/Ibu pada agenda yang insyaAllah akan dilaksanakan pada:
</p>
<table style="width: 100%; margin-left: 20px; margin-bottom: 15px; border-collapse: collapse;">
    <tr><td style="width: 25%; padding: 4px 0;">Hari / Tanggal</td><td style="width: 3%;">:</td><td>[Tentukan Hari & Tanggal]</td></tr>
    <tr><td style="padding: 4px 0;">Waktu</td><td>:</td><td>08.00 WIB - Selesai</td></tr>
    <tr><td style="padding: 4px 0;">Tempat</td><td>:</td><td>Gedung {lembaga}</td></tr>
    <tr><td style="padding: 4px 0;">Acara</td><td>:</td><td>{perihal}</td></tr>
</table>
<p style="text-indent: 30px; text-align: justify; line-height: 1.6;">
Mengingat pentingnya acara tersebut, kami sangat mengharapkan kehadiran tepat pada waktunya. Atas perhatian dan kerjasama Bapak/Ibu kami sampaikan terima kasih.
</p>
<p><em>Wassalamu\'alaikum Warahmatullahi Wabarakatuh.</em></p>',
                'is_active'  => true,
            ],
        ];

        foreach ($templates as $tmpl) {
            SuratTemplate::updateOrCreate(
                ['nama' => $tmpl['nama']],
                $tmpl
            );
        }
    }
}
