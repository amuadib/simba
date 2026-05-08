@php
    $menus = [
        [
            'route' => 'dashboard',
            'icon' => 'bi-speedometer2',
            'label' => 'Dashboard',
        ],
        [
            'route' => 'pembelajaran.presensi.*',
            'url' => 'pembelajaran.presensi.index',
            'icon' => 'bi-clock-history',
            'label' => 'Rekap Presensi',
        ],
        [
            'route' => 'pembelajaran.*',
            'url' => 'pembelajaran.index',
            'icon' => 'bi-journal-bookmark-fill',
            'label' => 'Pembelajaran',
        ],
        [
            'route' => 'siswa.*',
            'url' => 'siswa.index',
            'icon' => 'bi-people',
            'label' => 'Siswa',
        ],
        [
            'route' => 'rombel.*',
            'url' => 'rombel.index',
            'icon' => 'bi-diagram-3',
            'label' => 'Rombel',
        ],
        [
            'route' => 'jadwal.*',
            'url' => 'jadwal.index',
            'icon' => 'bi-calendar-week',
            'label' => 'Jadwal',
        ],
        [
            'route' => 'pelajaran.*',
            'url' => 'pelajaran.index',
            'icon' => 'bi-book',
            'label' => 'Pelajaran',
        ],
        [
            'route' => 'tahun_ajaran.*',
            'url' => 'tahun_ajaran.index',
            'icon' => 'bi-calendar',
            'label' => 'Tahun Ajaran',
        ],
        [
            'route' => 'pengguna.*',
            'url' => 'pengguna.index',
            'icon' => 'bi-person',
            'roles' => ['admin'],
            'label' => 'Pengguna',
        ],
        [
            'route' => 'settings.*',
            'url' => 'settings.index',
            'icon' => 'bi-gear',
            'label' => 'Pengaturan',
            'roles' => ['admin'],
        ],
        [
            'route' => 'database.*',
            'url' => 'database.index',
            'icon' => 'bi-database',
            'roles' => ['admin'],
            'label' => 'Database',
        ],
    ];
@endphp
<div class="offcanvas-lg offcanvas-start sidebar" id="sidebar">
    <div class="fw-bold p-3">Menu</div>

    @foreach ($menus as $menu)
        @if (isset($menu['roles']) && !in_array(Auth::user()->role, $menu['roles']))
            @continue
        @endif
        <a href="{{ route($menu['url'] ?? $menu['route']) }}" wire:navigate
            class="{{ request()->routeIs($menu['route']) ? 'active' : '' }}">
            <i class="bi {{ $menu['icon'] }}"></i> {{ $menu['label'] }}
        </a>
    @endforeach
</div>
