@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Pengaturan Aplikasi</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Konfigurasi informasi umum aplikasi</p>
        </div>
        <x-breadcrumb />
    </div>
    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
            @csrf

                <div class="mb-3">
                    <label>Nama Aplikasi</label>
                    <input type="text" name="nama_aplikasi" value="{{ setting('nama_aplikasi') }}" class="form-control">
                </div>

                <div class="mb-3">
                    <label>Nama Lembaga</label>
                    <input type="text" name="nama_lembaga" value="{{ setting('nama_lembaga') }}" class="form-control">
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>NPSN</label>
                        <input type="text" name="npsn" value="{{ setting('npsn') }}" class="form-control" placeholder="Contoh: 20512345">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Status Akreditasi</label>
                        <input type="text" name="akreditasi" value="{{ setting('akreditasi') }}" class="form-control" placeholder="Contoh: A / Unggul / B">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Website Lembaga</label>
                        <input type="text" name="website" value="{{ setting('website') }}" class="form-control" placeholder="https://sekolah.sch.id">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Alamat</label>
                    <textarea name="alamat" class="form-control">{{ setting('alamat') }}</textarea>
                </div>

                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ setting('email') }}" class="form-control">
                </div>

                <div class="mb-3">
                    <label>Telp</label>
                    <input type="text" name="telp" value="{{ setting('telp') }}" class="form-control">
                </div>

                <div class="mb-3">
                    <label>Kepala Lembaga</label>
                    <input type="text" name="kepala_lembaga" value="{{ setting('kepala_lembaga') }}" class="form-control" placeholder="Nama Kepala Lembaga">
                </div>

                <div class="mb-3">
                    <label>Logo</label><br>

                    <img src="{{ logo_url() }}" height="80" class="mb-2">

                    <input type="file" name="logo" class="form-control">
                </div>

                <div class="mb-3">
                    <label>TTD Kepala Lembaga</label><br>
                    @if (ttd_url())
                        <div class="p-2 border rounded d-inline-block mb-2 bg-light">
                            <img src="{{ ttd_url() }}" height="60" alt="TTD Kepala">
                        </div>
                    @endif
                    <input type="file" name="ttd" class="form-control" accept="image/*">
                    <small class="text-muted">Disarankan format PNG transparan.</small>
                </div>

                <div class="mb-3">
                    <label>Stempel Lembaga</label><br>
                    @if (stempel_url())
                        <div class="p-2 border rounded d-inline-block mb-2 bg-light">
                            <img src="{{ stempel_url() }}" height="60" alt="Stempel Lembaga">
                        </div>
                    @endif
                    <input type="file" name="stempel" class="form-control" accept="image/*">
                    <small class="text-muted">Disarankan format PNG transparan.</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Latitude</label>
                        <input type="text" name="lat" value="{{ setting('lat') }}" class="form-control" placeholder="Contoh: -7.536065">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Longitude</label>
                        <input type="text" name="lon" value="{{ setting('lon') }}" class="form-control" placeholder="Contoh: 110.592812">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Google Maps Embed (Iframe / URL)</label>
                    <input type="text" name="maps_embed" value="{{ setting('maps_embed') }}" class="form-control" placeholder='Paste kode <iframe>...</iframe> atau link Google Maps'>
                    <small class="text-muted">Dapat berupa kode embed iframe Google Maps atau URL langsung.</small>
                </div>

                @if (gmaps_embed_url())
                    <div class="mb-3">
                        <label>Pratinjau Peta</label>
                        <div class="border rounded overflow-hidden">
                            <iframe src="{{ gmaps_embed_url() }}" width="100%" height="220" style="border:0;" loading="lazy"></iframe>
                        </div>
                    </div>
                @endif

                <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold shadow-md">
                    <i class="bi bi-save2"></i>
                    <span>Simpan Pengaturan</span>
                </button>
            </form>
    </div>
@endsection
