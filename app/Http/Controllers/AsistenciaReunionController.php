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
            'telefono' => 'required|string|max:50',
            'slot' => 'required|integer',
            'token' => 'required|string',
        ]);

        $slot = (int) $request->slot;

        $tokenEsperado = hash_hmac('sha256', $session->id.'|'.$slot, config('app.key'));
        if (!hash_equals($tokenEsperado, $request->token)) {
            return redirect()->back()->withErrors(['error' => 'Token inválido.']);
        }

        $cedula = $this->normalizeDigits($request->cc);
        $correo = mb_strtolower(trim((string) $request->correo));
        $telefono = $this->normalizeDigits($request->telefono);

        $abogado = Abogado::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(cc, '.', ''), ',', ''), '-', ''), ' ', '') = ?",
                [$cedula]
            )
            ->whereRaw('LOWER(TRIM(correo)) = ?', [$correo])
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?",
                [$telefono]
            )
            ->first();

        if (!$abogado) {
            return redirect()->back()->withErrors([
                'error' => 'Los datos no coinciden con tu registro. Verifica cédula, correo y celular o comunícate con coordinación.',
            ])->withInput();
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

    private function normalizeDigits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
