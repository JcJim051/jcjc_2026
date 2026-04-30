<?php

namespace App\Http\Controllers;

use App\Models\Abogado;
use App\Models\AsistenciaSession;
use App\Models\Control;
use App\Models\Puestos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AsistenciaReunionController extends Controller
{
    private const FORM_TTL_SECONDS = 300; // 5 minutos

    public function panelReunion($token)
    {
        $session = AsistenciaSession::with('reunion')
            ->where('public_token', $token)
            ->where('activa', true)
            ->firstOrFail();

        $slot = (int) floor(Carbon::now('America/Bogota')->timestamp / 30);
        $signedPath = URL::temporarySignedRoute(
            'asistencia.reunion.form',
            Carbon::now('America/Bogota')->addSeconds(self::FORM_TTL_SECONDS),
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
        $puestos = Puestos::query()
            ->orderBy('mun')
            ->orderBy('nombre')
            ->get(['mun', 'nombre'])
            ->map(function ($p) {
                return trim(($p->mun ?? '') . ' - ' . ($p->nombre ?? ''), ' -');
            })
            ->filter()
            ->unique()
            ->values();

        return view('asistencia.session', compact('session', 'slot', 'hashToken', 'publicToken', 'puestos'));
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
            'telefono' => 'nullable|string|max:50',
            'puesto' => 'nullable|string|max:255',
            'slot' => 'required|integer',
            'token' => 'required|string',
        ]);

        $slot = (int) $request->slot;

        $tokenEsperado = hash_hmac('sha256', $session->id.'|'.$slot, config('app.key'));
        if (!hash_equals($tokenEsperado, $request->token)) {
            return redirect()->back()->withErrors(['error' => 'Token inválido.']);
        }

        $abogado = Abogado::where('cc', trim((string) $request->cc))->first();

        if (!$abogado) {
            return redirect()->back()->withErrors([
                'error' => 'No encontramos tu registro en el equipo. Verifica tu cédula con coordinación.',
            ]);
        }

        $codigoAbogado = (string) $abogado->id;
        $asistenciasPrevias = Control::where('codigo_abogado', $codigoAbogado)->count();
        $esPrimeraAsistencia = $asistenciasPrevias === 0;

        if ($esPrimeraAsistencia && $this->isMissingValue($abogado->telefono)) {
            $telefonoIngresado = trim((string) $request->telefono);
            if ($telefonoIngresado === '') {
                return redirect()->back()->withErrors([
                    'telefono' => 'Para tu primera asistencia debes registrar tu celular.',
                ])->withInput();
            }
        }

        // Completa automáticamente campos faltantes en caracterización (sin sobreescribir datos ya diligenciados).
        $correoIngresado = trim((string) $request->correo);
        $telefonoIngresado = trim((string) $request->telefono);
        $puestoIngresado = trim((string) $request->puesto);

        if ($this->isMissingValue($abogado->correo) && $correoIngresado !== '') {
            $abogado->correo = $correoIngresado;
        }
        if ($this->isMissingValue($abogado->telefono) && $telefonoIngresado !== '') {
            $abogado->telefono = $telefonoIngresado;
        }
        if ($this->isMissingValue($abogado->puesto) && $puestoIngresado !== '') {
            $abogado->puesto = $puestoIngresado;
        }
        $abogado->save();

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

    private function isMissingValue(?string $value): bool
    {
        $v = trim((string) $value);
        if ($v === '') {
            return true;
        }
        return in_array(mb_strtolower($v), ['n/d', 'nd', 'na', 'n.a.'], true);
    }
}
