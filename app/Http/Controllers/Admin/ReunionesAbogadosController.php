<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsistenciaSession;
use App\Models\Control;
use App\Models\Reunion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReunionesAbogadosController extends Controller
{
    public function index()
    {
        $reuniones = Reunion::withCount('abogados')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return view('admin.abogados_reuniones.index', compact('reuniones'));
    }

    public function create()
    {
        return view('admin.abogados_reuniones.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'lugar' => 'required|string|max:255',
            'aforo' => 'nullable|string|max:50',
            'motivo' => 'nullable|string|max:255',
        ]);

        $reunion = Reunion::create($data);
        AsistenciaSession::firstOrCreate(
            ['reunion_id' => $reunion->id],
            [
                'public_token' => Str::random(48),
                'activa' => true,
                'abierta_en' => Carbon::now('America/Bogota'),
            ]
        );

        return redirect()->route('admin.abogados_reuniones.qr', $reunion->id)->with('info', 'Reunión creada con éxito');
    }

    public function qrAsistencia($reunion)
    {
        $reunion = Reunion::findOrFail($reunion);

        $session = AsistenciaSession::firstOrCreate(
            ['reunion_id' => $reunion->id],
            [
                'public_token' => Str::random(48),
                'activa' => true,
                'abierta_en' => Carbon::now('America/Bogota'),
            ]
        );

        if (!$session->activa) {
            $session->update([
                'activa' => true,
                'abierta_en' => Carbon::now('America/Bogota'),
                'cerrada_en' => null,
            ]);
        }

        if (empty($session->public_token)) {
            $session->public_token = Str::random(48);
            $session->save();
        }

        $panelUrl = route('asistencia.reunion.panel', ['token' => $session->public_token]);
        $asistentesRegistrados = Control::where('asistencia_session_id', $session->id)->count();

        return view('admin.abogados_reuniones.qr', compact('reunion', 'session', 'panelUrl', 'asistentesRegistrados'));
    }
}
