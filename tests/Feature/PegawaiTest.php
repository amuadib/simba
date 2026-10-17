<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pegawai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

class PegawaiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_pegawai_page(): void
    {
        $response = $this->get('/pegawai');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_pegawai_page(): void
    {
        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->actingAs($user)
            ->get('/pegawai')
            ->assertStatus(200)
            ->assertSee('Data Pegawai');
    }

    public function test_pegawai_model_uses_uuid_and_accessors(): void
    {
        $pegawai = Pegawai::create([
            'nama' => 'Budi Santoso',
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Pd.',
            'niy' => '123456789',
            'jenis_kelamin' => 'L',
            'nik' => '3309012345678901',
            'tmt' => '2020-01-01',
            'lembaga_id' => 1,
            'status' => '1',
        ]);

        $this->assertNotEmpty($pegawai->id);
        $this->assertEquals(36, strlen($pegawai->id));
        $this->assertEquals('l', $pegawai->jenis_kelamin); // mutator to lowercase
        $this->assertEquals('Dr. Budi Santoso, M.Pd.', $pegawai->nama_lengkap);
        $this->assertEquals('Laki-laki', $pegawai->jenis_kelamin_label);
        $this->assertEquals('Aktif', $pegawai->status_label);
        $this->assertNotEmpty($pegawai->nama_lembaga);

        $pegawai->delete();
    }

    public function test_can_create_pegawai_via_livewire(): void
    {
        Storage::fake('public');

        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);

        $this->actingAs($user);

        $foto = UploadedFile::fake()->image('guru.jpg');

        Volt::test('pegawai.index')
            ->set('nama', 'Siti Aminah')
            ->set('gelar_belakang', 'S.Pd.')
            ->set('form_jenis_kelamin', 'p')
            ->set('form_lembaga_id', 2)
            ->set('form_status', '1')
            ->set('niy', '987654321')
            ->set('nuptk', '1234567890123456')
            ->set('foto', $foto)
            ->call('store')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Pegawai berhasil ditambahkan');

        $this->assertDatabaseHas('pegawai', [
            'nama' => 'Siti Aminah',
            'niy' => '987654321',
            'nuptk' => '1234567890123456',
            'jenis_kelamin' => 'p',
            'lembaga_id' => 2,
            'status' => '1',
        ]);

        $p = Pegawai::where('nama', 'Siti Aminah')->first();
        $this->assertNotNull($p->foto);
        Storage::disk('public')->assertExists($p->foto);
    }

    public function test_can_update_pegawai_via_livewire(): void
    {
        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $pegawai = Pegawai::create([
            'nama' => 'Ahmad Dahlan',
            'jenis_kelamin' => 'l',
            'lembaga_id' => 1,
            'status' => '1',
        ]);

        Volt::test('pegawai.index')
            ->call('edit', $pegawai->id)
            ->set('nama', 'KH. Ahmad Dahlan')
            ->set('gelar_depan', 'KH.')
            ->set('nuptk', '9876543210987654')
            ->set('form_lembaga_id', 3)
            ->set('form_status', '2')
            ->call('update')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Pegawai berhasil diperbarui');

        $this->assertDatabaseHas('pegawai', [
            'id' => $pegawai->id,
            'nama' => 'KH. Ahmad Dahlan',
            'gelar_depan' => 'KH.',
            'nuptk' => '9876543210987654',
            'lembaga_id' => 3,
            'status' => '2',
        ]);
    }

    public function test_can_delete_pegawai_via_livewire(): void
    {
        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $pegawai = Pegawai::create([
            'nama' => 'Pegawai Dihapus',
            'jenis_kelamin' => 'l',
            'status' => '1',
        ]);

        Volt::test('pegawai.index')
            ->call('delete', $pegawai->id)
            ->assertDispatched('toast', message: 'Pegawai berhasil dihapus');

        $this->assertDatabaseMissing('pegawai', [
            'id' => $pegawai->id,
        ]);
    }

    public function test_can_bulk_update_and_bulk_delete_pegawai(): void
    {
        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $p1 = Pegawai::create(['nama' => 'Pegawai 1', 'jenis_kelamin' => 'l', 'lembaga_id' => 1, 'status' => '1']);
        $p2 = Pegawai::create(['nama' => 'Pegawai 2', 'jenis_kelamin' => 'p', 'lembaga_id' => 1, 'status' => '1']);

        // Bulk Update Status and Lembaga
        Volt::test('pegawai.index')
            ->set('checkedPegawai', [$p1->id, $p2->id])
            ->set('bulkStatus', '3')
            ->set('bulkLembagaId', '2')
            ->call('bulkUpdate')
            ->assertDispatched('toast', message: 'Data pegawai berhasil diperbarui secara massal');

        $this->assertDatabaseHas('pegawai', ['id' => $p1->id, 'status' => '3', 'lembaga_id' => 2]);
        $this->assertDatabaseHas('pegawai', ['id' => $p2->id, 'status' => '3', 'lembaga_id' => 2]);

        // Bulk Delete with confirmation text
        $test = Volt::test('pegawai.index')
            ->set('checkedPegawai', [$p1->id, $p2->id])
            ->call('prepareBulkDelete');

        $confirmCode = $test->get('randomConfirmString');

        $test->set('confirmText', $confirmCode)
            ->call('bulkDelete')
            ->assertDispatched('toast', message: 'Data pegawai berhasil dihapus');

        $this->assertDatabaseMissing('pegawai', ['id' => $p1->id]);
        $this->assertDatabaseMissing('pegawai', ['id' => $p2->id]);
    }

    public function test_pegawai_export_and_import(): void
    {
        $pegawai = Pegawai::create([
            'niy' => '99887766',
            'nama' => 'Prof. Dr. Ir. Soekarno',
            'jenis_kelamin' => 'l',
            'lembaga_id' => 1,
            'status' => '1',
        ]);

        $export = new \App\Exports\PegawaiExport([$pegawai->id]);
        $rows = $export->collection();
        $this->assertCount(1, $rows);
        $this->assertContains('Lembaga', $export->headings());
        $this->assertContains('NUPTK', $export->headings());

        $templateExport = new \App\Exports\PegawaiExport([], 'template-import');
        $this->assertCount(1, $templateExport->collection());
        $this->assertContains('Lembaga ID', $templateExport->headings());
        $this->assertContains('NUPTK', $templateExport->headings());
    }

    public function test_auto_generates_niy_when_not_provided(): void
    {
        // Pegawai 1: Pria, TMT: 2025-06-01 -> Urutan 1 (001)
        $p1 = Pegawai::create([
            'nama' => 'Guru Laki 1',
            'jenis_kelamin' => 'l',
            'tmt' => '2025-06-01',
            'lembaga_id' => 1,
            'status' => '1',
        ]);
        $this->assertEquals('202506001001', $p1->niy);

        // Pegawai 2: Pria, TMT: 2025-06-15 -> Urutan 2 (002)
        $p2 = Pegawai::create([
            'nama' => 'Guru Laki 2',
            'jenis_kelamin' => 'l',
            'tmt' => '2025-06-15',
            'lembaga_id' => 1,
            'status' => '1',
        ]);
        $this->assertEquals('202506001002', $p2->niy);

        // Pegawai 3: Wanita, TMT: 2025-06-01 -> Urutan 3 (003), kode gender 002
        $p3 = Pegawai::create([
            'nama' => 'Guru Perempuan 1',
            'jenis_kelamin' => 'p',
            'tmt' => '2025-06-01',
            'lembaga_id' => 1,
            'status' => '1',
        ]);
        $this->assertEquals('202506002003', $p3->niy);

        // Pegawai 4: Wanita, TMT: 2025-07-20 -> Urutan 4 (004), kode gender 002
        $p4 = Pegawai::create([
            'nama' => 'Guru Perempuan 2',
            'jenis_kelamin' => 'p',
            'tmt' => '2025-07-20',
            'lembaga_id' => 1,
            'status' => '1',
        ]);
        $this->assertEquals('202507002004', $p4->niy);

        // Manual Pegawai dengan urutan 040 -> generate berikutnya harus lanjut ke 041
        Pegawai::create([
            'nama' => 'Guru Senior',
            'jenis_kelamin' => 'l',
            'niy' => '201601001040',
            'status' => '1',
        ]);

        $p5 = Pegawai::create([
            'nama' => 'Guru Baru Lanjutan',
            'jenis_kelamin' => 'l',
            'tmt' => '2026-10-01',
            'status' => '1',
        ]);
        $this->assertEquals('202610001041', $p5->niy);
    }

    public function test_livewire_creates_pegawai_with_auto_niy_when_empty(): void
    {
        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        Volt::test('pegawai.index')
            ->set('nama', 'Guru Otomatis NIY')
            ->set('form_jenis_kelamin', 'l')
            ->set('tmt', '2024-08-10')
            ->set('form_lembaga_id', 1)
            ->set('form_status', '1')
            ->set('niy', '') // Kosongkan NIY
            ->call('store')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Pegawai berhasil ditambahkan');

        $this->assertDatabaseHas('pegawai', [
            'nama' => 'Guru Otomatis NIY',
            'niy' => '202408001001',
            'jenis_kelamin' => 'l',
        ]);
    }

    public function test_sk_penugasan_model_and_relations(): void
    {
        $pegawai = Pegawai::create([
            'nama' => 'Guru Multi Lembaga',
            'jenis_kelamin' => 'l',
            'lembaga_id' => 1,
            'status' => '1',
        ]);

        $sk1 = $pegawai->skPenugasan()->create([
            'lembaga_id' => 1,
            'nomor_sk' => '001/SK-SDI/2025',
            'tanggal_sk' => '2025-01-01',
            'tmt_tugas' => '2025-01-02',
            'jabatan' => 3, // Guru Kelas
            'is_induk' => true,
            'status' => '1',
        ]);

        $sk2 = $pegawai->skPenugasan()->create([
            'lembaga_id' => 2,
            'nomor_sk' => '002/SK-SMPI/2025',
            'tanggal_sk' => '2025-02-01',
            'tmt_tugas' => '2025-02-05',
            'jabatan' => 4, // Guru Mapel
            'is_induk' => false,
            'status' => '1',
        ]);

        $this->assertCount(2, $pegawai->skPenugasan);
        $this->assertCount(2, $pegawai->penugasanAktif);
        $this->assertEquals(1, $pegawai->penugasanInduk->lembaga_id);
        $this->assertEquals(3, $sk1->jabatan);
        $this->assertEquals('Guru Kelas', $sk1->nama_jabatan);
        $this->assertEquals('Guru Mapel', $sk2->nama_jabatan);
        $this->assertEquals('Aktif', $sk1->status_label);
        $this->assertStringContainsString('SDI', $pegawai->daftar_nama_lembaga);
        $this->assertStringContainsString('SMPI', $pegawai->daftar_nama_lembaga);
        $this->assertStringContainsString('Guru Kelas', $pegawai->daftar_nama_lembaga);
    }

    public function test_can_manage_multiple_sk_penugasan_via_livewire(): void
    {
        Storage::fake('public');

        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        // 1. Create Pegawai
        $pegawai = Pegawai::create([
            'nama' => 'Ustadz Abdullah',
            'jenis_kelamin' => 'l',
            'lembaga_id' => 1,
            'status' => '1',
        ]);

        // Automatically has initial penugasan
        $initialSk = $pegawai->skPenugasan()->create([
            'lembaga_id' => 1,
            'nomor_sk' => '010/YPIB/2024',
            'tmt_tugas' => '2024-07-01',
            'jabatan' => 3,
            'is_induk' => true,
            'status' => '1',
        ]);

        // 2. Add second SK penugasan at Lembaga 2
        $fileSk = UploadedFile::fake()->create('sk_penugasan_smp.pdf', 100, 'application/pdf');

        Volt::test('pegawai.index')
            ->call('kelolaSk', $pegawai->id)
            ->set('sk_lembaga_id', 2)
            ->set('sk_nomor', '025/YPIB/2025')
            ->set('sk_tanggal', '2025-01-10')
            ->set('sk_tmt_tugas', '2025-01-15')
            ->set('sk_jabatan', 4)
            ->set('sk_is_induk', false)
            ->set('sk_status', '1')
            ->set('sk_file', $fileSk)
            ->call('saveSk')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'SK Penugasan berhasil ditambahkan');

        $this->assertDatabaseHas('sk_penugasan', [
            'pegawai_id' => $pegawai->id,
            'lembaga_id' => 2,
            'nomor_sk' => '025/YPIB/2025',
            'jabatan' => 4,
            'is_induk' => false,
        ]);

        $secondSk = \App\Models\SkPenugasan::where('nomor_sk', '025/YPIB/2025')->first();
        $this->assertNotNull($secondSk->file_sk);
        Storage::disk('public')->assertExists($secondSk->file_sk);

        // 3. Edit second SK penugasan
        Volt::test('pegawai.index')
            ->set('selectedPegawaiId', $pegawai->id)
            ->call('editSk', $secondSk->id)
            ->set('sk_jabatan', 2) // Ubah ke Kepala Sekolah
            ->call('saveSk')
            ->assertDispatched('toast', message: 'SK Penugasan berhasil diperbarui');

        $this->assertDatabaseHas('sk_penugasan', [
            'id' => $secondSk->id,
            'jabatan' => 2,
        ]);

        // 4. Set second SK as Lembaga Induk
        Volt::test('pegawai.index')
            ->set('selectedPegawaiId', $pegawai->id)
            ->call('setIndukSk', $secondSk->id)
            ->assertDispatched('toast');

        $this->assertTrue((bool) $secondSk->fresh()->is_induk);
        $this->assertFalse((bool) $initialSk->fresh()->is_induk);
        $this->assertEquals(2, $pegawai->fresh()->lembaga_id);

        // 5. Delete initial SK
        Volt::test('pegawai.index')
            ->set('selectedPegawaiId', $pegawai->id)
            ->call('deleteSk', $initialSk->id)
            ->assertDispatched('toast', message: 'SK Penugasan berhasil dihapus');

        $this->assertDatabaseMissing('sk_penugasan', [
            'id' => $initialSk->id,
        ]);
    }

    public function test_filter_by_lembaga_finds_pegawai_with_multiple_penugasan(): void
    {
        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $pegawai = Pegawai::create([
            'nama' => 'Guru Multi Satmikal',
            'jenis_kelamin' => 'p',
            'lembaga_id' => 1,
            'status' => '1',
        ]);

        // Tugas di lembaga 1 (induk) dan lembaga 2 (tambahan)
        $pegawai->skPenugasan()->create([
            'lembaga_id' => 1,
            'is_induk' => true,
            'status' => '1',
        ]);

        $pegawai->skPenugasan()->create([
            'lembaga_id' => 2,
            'is_induk' => false,
            'status' => '1',
        ]);

        // Filter lembaga 1 -> muncul
        Volt::test('pegawai.index')
            ->set('lembaga_id', 1)
            ->assertSee('Guru Multi Satmikal');

        // Filter lembaga 2 -> muncul juga
        Volt::test('pegawai.index')
            ->set('lembaga_id', 2)
            ->assertSee('Guru Multi Satmikal');

        // Filter lembaga 3 -> tidak muncul
        Volt::test('pegawai.index')
            ->set('lembaga_id', 3)
            ->assertDontSee('Guru Multi Satmikal');
    }

    public function test_filter_by_jabatan_finds_pegawai(): void
    {
        $user = User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $p1 = Pegawai::create([
            'nama' => 'Pak Kepala Sekolah',
            'jenis_kelamin' => 'l',
            'lembaga_id' => 1,
            'status' => '1',
        ]);
        $p1->skPenugasan()->create([
            'lembaga_id' => 1,
            'jabatan' => 2, // Kepala Sekolah
            'is_induk' => true,
            'status' => '1',
        ]);

        $p2 = Pegawai::create([
            'nama' => 'Ibu Guru Kelas Tiga',
            'jenis_kelamin' => 'p',
            'lembaga_id' => 1,
            'status' => '1',
        ]);
        $p2->skPenugasan()->create([
            'lembaga_id' => 1,
            'jabatan' => 3, // Guru Kelas
            'is_induk' => true,
            'status' => '1',
        ]);

        // Filter jabatan 2 (Kepala Sekolah) -> Pak Kepala Sekolah ada, Ibu Guru Kelas tidak ada
        Volt::test('pegawai.index')
            ->set('jabatan_id', 2)
            ->assertSee('Pak Kepala Sekolah')
            ->assertDontSee('Ibu Guru Kelas Tiga');

        // Filter jabatan 3 (Guru Kelas) -> Ibu Guru Kelas ada, Pak Kepala Sekolah tidak ada
        Volt::test('pegawai.index')
            ->set('jabatan_id', 3)
            ->assertSee('Ibu Guru Kelas Tiga')
            ->assertDontSee('Pak Kepala Sekolah');
    }
}

