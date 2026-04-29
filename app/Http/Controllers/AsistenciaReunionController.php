<?php

namespace App\Http\Controllers;

use App\Models\Abogado;
use App\Models\AsistenciaSession;
use App\Models\Control;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AsistenciaReunionController extends Controller
{
    public function panelReunion($token)
    {
        $session = AsistenciaSession::with('reunion')
            ->where('public_token', $token)
            ->where('activa', true)
            ->firstOrFail();

        $slot = (int) floor(Carbon::now('America/Bogota')->timestamp / 30);
        $signedPath = URL::temporarySignedRoute(
            'asistencia.reunion.form',
            Carbon::now('America/Bogota')->addSeconds(30),
            ['token' => $token, 'slot' => $slot],
            false
        );
        $formUrl = url($signedPath);

        $asistentesRegistrados = Control::where('asistencia_session_id', $session->id)->count();

        return view('asistencia.panel_qr', compact('session', 'formUrl', 'asistentesRegistrados'));
    }

    public function mostrarFormularioSesion(Request $request, $publicToken)
    {
        $session = AsistenciaSession::with('reunion')
            ->where('public_token', $publicToken)
            ->where('activa', true)
            ->firstOrFail();

        $slot = (int) $request->query('slot');
        $hashToken = hash_hmac('sha256', $session->id.'|'.$slot, config('app.key'));

        return view('asistencia.session', compact('session', 'slot', 'hashToken', 'publicToken'));
    }

    public function procesarFormularioSesion(Request $request, $publicToken)
    {
        $session = AsistenciaSession::with('reunion')
            ->where('public_token', $publicToken)
            ->where('activa', true)
            ->firstOrFail();

        $request->validate([
            'cc' => 'required|string|max:40',
            'correo' => 'required|email|max:255',
            'slot' => 'required|integer',
            'token' => 'required|string',
        ]);

        $slot = (int) $request->slot;
        $nowSlot = (int) floor(Carbon::now('America/Bogota')->timestamp / 30);
        if (abs($nowSlot - $slot) > 1) {
            return redirect()->back()->withErrors(['error' => 'El QR expiró. Escanéalo nuevamente.']);
        }

        $tokenEsperado = hash_hmac('sha256', $session->id.'|'.$slot, config('app.key'));
        if (!hash_equals($tokenEsperado, $request->token)) {
            return redirect()->back()->withErrors(['error' => 'Token inválido.']);
        }

        $abogado = Abogado::where('cc', trim((string) $request->cc))
            ->where('correo', trim((string) $request->correo))
            ->first();

        if (!$abogado) {
            return redirect()->back()->withErrors([
                'error' => 'No encontramos tu registro en el equipo. Verifica cédula/correo con coordinación.',
            ]);
        }

        $codigoAbogado = (string) $abogado->id;
        $yaRegistrado = Control::where('asistencia_session_id', $session->id)
            ->where('codigo_abogado', $codigoAbogado)
            ->exists();

        if ($yaRegistrado) {
            return redirect()->back()->withErrors(['error' => 'Ya registraste asistencia en esta reunión.']);
        }

        $motivo = 'Asistencia reunión #'.$session->reunion->id;
        if (!empty($session->reunion->lugar)) {
            $motivo .= ' - '.$session->reunion->lugar;
        }

        Control::create([
            'codigo_abogado' => $codigoAbogado,
            'asistencia_session_id' => $session->id,
            'fecha' => Carbon::now('America/Bogota')->format('Y-m-d H:i:s'),
            'lugar' => $session->reunion->lugar,
            'motivo' => $motivo,
        ]);

        $session->reunion->abogados()->syncWithoutDetaching([$abogado->id]);

        return redirect()->back()->with('info', 'Asistencia registrada con éxito.');
    }
}

