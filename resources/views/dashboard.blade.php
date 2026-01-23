@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Total Siswa</small>
                <h4>320</h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Hadir</small>
                <h4>298</h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Izin</small>
                <h4>12</h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Alpa</small>
                <h4>10</h4>
            </div>
        </div>
    </div>

    <!-- GRAPH -->
    <div class="card p-3 shadow-sm">
        <h6>Grafik Kehadiran</h6>
        <div class="d-flex justify-content-center align-items-center rounded" style="height:300px; background:#020617;">
            <span class="text-muted">[ Placeholder Grafik ]</span>
        </div>
    </div>

@endsection
