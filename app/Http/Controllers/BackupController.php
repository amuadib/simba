<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index()
    {
        $tables = [
            'anggota_pembelajaran',
            'pelajaran',
            'pembelajaran',
            'presensi',
            'rombel',
            'siswa',
            'siswa_tag',
            'tag',
            'tahun_ajaran',
            'users',
        ];

        return view('admin.backup', compact('tables'));
    }

    public function export(Request $request)
    {
        $tables = $request->validate([
            'tables' => 'required|array'
        ])['tables'];

        $data = [];
        foreach ($tables as $table) {
            $data[$table] = DB::table($table)->get();
        }

        $payload = [
            'meta' => [
                'app' => config('app.name'),
                'exported_at' => now()->toDateTimeString(),
                'tables' => $tables,
            ],
            'data' => $data,
        ];

        $filename = 'backup_' . now()->format('Ymd_His') . '.json';

        return Response::make(
            json_encode($payload, JSON_PRETTY_PRINT),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=$filename",
            ]
        );
    }
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json'
        ]);

        $json = json_decode(
            file_get_contents($request->file('file')->getRealPath()),
            true
        );

        DB::transaction(function () use ($json) {

            DB::statement('SET FOREIGN_KEY_CHECKS=0'); //MySQL
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::statement('PRAGMA foreign_keys = OFF'); // SQLite

            foreach ($json['meta']['tables'] as $table) {
                DB::table($table)->truncate();
            }

            foreach ($json['data'] as $table => $rows) {
                DB::table($table)->insert($rows);
            }

            DB::statement('PRAGMA foreign_keys = ON');
        });

        return back()->with('success', 'Import berhasil');
    }
}
