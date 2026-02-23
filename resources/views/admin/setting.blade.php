@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Pengaturan Aplikasi</h4>
            <small class="text-muted">Pengaturan Aplikasi</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
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
                    <label>Logo</label><br>

                    @if (setting('logo'))
                        <img src="{{ asset('storage/' . setting('logo')) }}" height="80" class="mb-2">
                    @endif

                    <input type="file" name="logo" class="form-control">
                </div>

                <button class="btn btn-primary">Simpan</button>
            </form>

        </div>
    </div>
@endsection
