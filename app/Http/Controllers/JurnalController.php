<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Pembelajaran;
use Illuminate\Http\Request;

class JurnalController extends Controller
{
    public function index(Pembelajaran $pembelajaran)
    {
        $jurnals = Jurnal::with('pembelajaran.pelajaran')
            ->where('pembelajaran_id', $pembelajaran->id)
            ->orderByDesc('tanggal')
            ->paginate(10);

        return view('pembelajaran.jurnal.index', compact('pembelajaran', 'jurnals'));
    }

    public function store(Pembelajaran $pembelajaran, Request $request)
    {
        $data = $request->validate([
            'tanggal' => 'required|date',
            'materi' => 'required',
        ]);
        $data['pembelajaran_id'] = $pembelajaran->id;
        $jurnal = Jurnal::create($data);

        return response()->json([
            'id' => $jurnal->id,
            'tanggal' => $jurnal->tanggal->format('d F Y'),
            'materi' => $jurnal->materi,
        ]);
    }

    public function update(Request $request, Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $data = $request->validate([
            'field' => 'required',
            'value' => 'required',
        ]);

        $jurnal->update([
            $request->field => $request->value,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $jurnal->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
