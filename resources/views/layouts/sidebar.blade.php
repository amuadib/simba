@php
    $menuGroups = [
        'Utama' => [
            [
                'route' => 'dashboard',
                'url' => 'dashboard',
                'icon' => 'bi-speedometer2',
                'label' => 'Dashboard',
            ],
        ],
        'Akademik & SDM' => [
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
                'label' => 'Data Siswa',
            ],
            [
                'route' => 'pegawai.*',
                'url' => 'pegawai.index',
                'icon' => 'bi-person-badge',
                'label' => 'Data Pegawai',
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
                'label' => 'Jadwal Pelajaran',
            ],
            [
                'route' => 'pelajaran.*',
                'url' => 'pelajaran.index',
                'icon' => 'bi-book',
                'label' => 'Mata Pelajaran',
            ],
        ],
        'Sistem & Master' => [
            [
                'route' => 'tahun_ajaran.*',
                'url' => 'tahun_ajaran.index',
                'icon' => 'bi-calendar2-range',
                'label' => 'Tahun Ajaran',
            ],
            [
                'route' => 'pengguna.*',
                'url' => 'pengguna.index',
                'icon' => 'bi-person-gear',
                'roles' => ['admin'],
                'label' => 'Manajemen Pengguna',
            ],
            [
                'route' => 'settings.*',
                'url' => 'settings.index',
                'icon' => 'bi-gear-wide-connected',
                'label' => 'Pengaturan Lembaga',
                'roles' => ['admin'],
            ],
            [
                'route' => 'database.*',
                'url' => 'database.index',
                'icon' => 'bi-database-check',
                'roles' => ['admin'],
                'label' => 'Database Backup',
            ],
        ],
    ];
@endphp

<aside class="offcanvas-lg offcanvas-start w-64 lg:w-68 flex-shrink-0 bg-white/95 dark:bg-[#07162b]/95 backdrop-blur-xl border-r border-slate-200 dark:border-cyan-500/15 flex flex-col h-full shadow-lg lg:shadow-none z-50" 
       tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">

    {{-- Offcanvas Header for Mobile --}}
    <div class="p-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 lg:hidden">
        <div class="flex items-center gap-2">
            <img src="{{ logo_url() }}" alt="Logo" class="w-8 h-8 object-contain">
            <span class="font-bold text-gradient-logo font-display">SIMBA</span>
        </div>
        <button type="button" class="btn-close dark:btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
    </div>

    {{-- Menu List (Scrollable) --}}
    <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        @foreach ($menuGroups as $groupLabel => $items)
            @php
                // Filter items by role
                $visibleItems = array_filter($items, function($item) {
                    return !isset($item['roles']) || in_array(Auth::user()->role, $item['roles']);
                });
            @endphp

            @if (count($visibleItems) > 0)
                <div>
                    <div class="px-3 mb-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-cyan-400/70 font-display">
                        {{ $groupLabel }}
                    </div>

                    <div class="space-y-1">
                        @foreach ($visibleItems as $menu)
                            @php
                                $isActive = request()->routeIs($menu['route']);
                            @endphp

                            <a href="{{ route($menu['url'] ?? $menu['route']) }}" 
                               wire:navigate
                               class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 text-decoration-none relative
                                      {{ $isActive 
                                          ? 'bg-gradient-to-r from-sky-500/15 via-cyan-500/10 to-emerald-500/10 text-sky-600 dark:text-cyan-300 font-semibold border-l-4 border-cyan-500 shadow-sm' 
                                          : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-sky-600 dark:hover:text-cyan-400' }}">
                                
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center transition-transform group-hover:scale-110 
                                             {{ $isActive 
                                                 ? 'bg-gradient-to-tr from-sky-500 to-cyan-400 text-white shadow-sm shadow-cyan-500/40' 
                                                 : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 group-hover:bg-cyan-500/10 group-hover:text-cyan-500' }}">
                                    <i class="bi {{ $menu['icon'] }} text-sm"></i>
                                </span>

                                <span class="truncate">{{ $menu['label'] }}</span>

                                @if ($isActive)
                                    <span class="ml-auto w-1.5 h-1.5 rounded-full bg-cyan-400 shadow-[0_0_8px_#22d3ee]"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Bottom Brand Card --}}
    <div class="p-3 m-3 rounded-2xl bg-gradient-to-tr from-sky-500/10 via-cyan-500/5 to-emerald-500/10 border border-slate-200/80 dark:border-cyan-500/20 backdrop-blur-md">
        <div class="flex items-center gap-2.5">
            <div class="p-1 rounded-xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-cyan-500/30">
                <img src="{{ logo_url() }}" alt="Emblem" class="w-7 h-7 object-contain">
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-bold truncate text-slate-800 dark:text-slate-100 font-display">
                    {{ config('local.app.singkatan') ?? 'SIMBA' }}
                </div>
                <div class="text-[10px] text-emerald-600 dark:text-emerald-400 truncate font-semibold">
                    v2.0 • Terintegrasi
                </div>
            </div>
        </div>
    </div>
</aside>
