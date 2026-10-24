<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Siswa;
use App\Models\SiswaLembaga;
use App\Models\Rombel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SiswaLembagaTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_siswa_auto_creates_initial_siswa_lembaga(): void
    {
        $rombel = Rombel::create([
            'nama' => 'Kelas 1A',
            'tingkat' => 1,
            'lembaga_id' => 1,
        ]);

        $siswa = Siswa::create([
            'nama' => 'Ahmad Fulan',
            'status' => 1,
            'rombel_id' => $rombel->id,
            'lembaga_id' => 1,
            'nis' => '1001',
        ]);

        $this->assertDatabaseHas('siswa_lembaga', [
            'siswa_id' => $siswa->id,
            'lembaga_id' => 1,
            'is_induk' => true,
            'status' => 1,
            'nis_lokal' => '1001',
        ]);

        $this->assertEquals(1, $siswa->keanggotaanLembaga()->count());
        $this->assertNotNull($siswa->lembagaInduk);
        $this->assertEquals(1, $siswa->lembagaInduk->lembaga_id);
    }

    public function test_marking_second_lembaga_as_induk_syncs_siswa_lembaga_id(): void
    {
        $rombel1 = Rombel::create(['nama' => 'Kelas 7A', 'tingkat' => 7, 'lembaga_id' => 2]);
        $rombel2 = Rombel::create(['nama' => 'Asrama Putra', 'tingkat' => 1, 'lembaga_id' => 3]);

        $siswa = Siswa::create([
            'nama' => 'Santri Budi',
            'status' => 1,
            'rombel_id' => $rombel1->id,
            'lembaga_id' => 2,
        ]);

        $sl2 = SiswaLembaga::create([
            'siswa_id' => $siswa->id,
            'lembaga_id' => 3,
            'rombel_id' => $rombel2->id,
            'nis_lokal' => 'P-2024-001',
            'is_induk' => true,
            'status' => 1,
        ]);

        $siswa->refresh();
        $this->assertEquals(3, $siswa->lembaga_id);
        $this->assertEquals($rombel2->id, $siswa->rombel_id);

        // Previous membership must now have is_induk = false
        $sl1 = SiswaLembaga::where('siswa_id', $siswa->id)->where('lembaga_id', 2)->first();
        $this->assertFalse((bool) $sl1->is_induk);
    }

    public function test_setting_status_inactive_auto_fills_tmt_keluar(): void
    {
        $rombel = Rombel::create(['nama' => 'Kelas 9B', 'tingkat' => 9, 'lembaga_id' => 1]);

        $siswa = Siswa::create([
            'nama' => 'Siti Aisyah',
            'status' => 1,
            'rombel_id' => $rombel->id,
            'lembaga_id' => 1,
        ]);

        $sl = $siswa->lembagaInduk;
        $this->assertNull($sl->tmt_keluar);

        // Set status to 2 (Lulus)
        $sl->update(['status' => 2]);
        $sl->refresh();

        $this->assertNotNull($sl->tmt_keluar);
        $this->assertEquals(now()->toDateString(), $sl->tmt_keluar->toDateString());
    }

    public function test_user_can_manage_keanggotaan_lembaga_via_livewire(): void
    {
        $user = \App\Models\User::factory()->make([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $rombel1 = Rombel::create(['nama' => 'Kelas 10 IPA', 'tingkat' => 10, 'lembaga_id' => 3]);
        $rombel2 = Rombel::create(['nama' => 'Madin Ula', 'tingkat' => 1, 'lembaga_id' => 1]);

        $siswa = Siswa::create([
            'nama' => 'Muhammad Hafiz',
            'status' => 1,
            'rombel_id' => $rombel1->id,
            'lembaga_id' => 3,
            'nis' => 'SMA-101',
        ]);

        \Livewire\Volt\Volt::actingAs($user)
            ->test('siswa.index')
            ->assertSee('Muhammad Hafiz')
            ->call('show', $siswa->id)
            ->assertSet('action', 'show')
            ->assertSet('showId', $siswa->id)
            ->set('sl_lembaga_id', 1)
            ->set('sl_rombel_id', $rombel2->id)
            ->set('sl_nis_lokal', 'MD-055')
            ->set('sl_status', 1)
            ->set('sl_is_induk', false)
            ->call('saveSl')
            ->assertDispatched('toast');

        $this->assertEquals(2, $siswa->keanggotaanLembaga()->count());
        $this->assertDatabaseHas('siswa_lembaga', [
            'siswa_id' => $siswa->id,
            'lembaga_id' => 1,
            'nis_lokal' => 'MD-055',
            'is_induk' => false,
        ]);

        // Now set the second lembaga as Induk
        $secondSl = SiswaLembaga::where('siswa_id', $siswa->id)->where('lembaga_id', 1)->first();
        \Livewire\Volt\Volt::actingAs($user)
            ->test('siswa.index')
            ->call('setIndukSl', $secondSl->id)
            ->assertDispatched('toast');

        $siswa->refresh();
        $this->assertEquals(1, $siswa->lembaga_id);
        $this->assertEquals($rombel2->id, $siswa->rombel_id);
    }
}
