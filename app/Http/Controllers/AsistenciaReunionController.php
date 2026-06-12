<?php

namespace App\Http\Controllers;

use App\Models\Abogado;
use App\Models\AbogadoContactUpdate;
use App\Models\AsistenciaSession;
use App\Models\Control;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AsistenciaReunionController extends Controller
{
    private const FORM_TTL_SECONDS = 300; // 5 minutos

    public function panelReunion($token)
    {
        $session = $this->activeSession($token);

        $slot = (int) floor(Carbon::now('America/Bogota')->timestamp / 30);
        $signedPath = URL::temporarySignedRoute(
            'asistencia.reunion.form',
            Carbon::now('America/Bogota')->addSeconds(self::FORM_TTL_SECONDS),
            ['token' => $token, 'slot' => $slot],
            false
        );
        $formUrl = url($signedPath);
        $svg = (new Writer(
            new ImageRenderer(new RendererStyle(700, 3), new SvgImageBackEnd())
        ))->writeString($formUrl);
        $qrSvg = trim(substr($svg, strpos($svg, "\n") + 1));

        $asistentesRegistrados = Control::where('asistencia_session_id', $session->id)->count();

        return view('asistencia.panel_qr', compact('session', 'formUrl', 'qrSvg', 'asistentesRegistrados'));
    }

    public function mostrarFormularioSesion(Request $request, $publicToken)
    {
        $session = $this->activeSession($publicToken);
        $slot = (int) $request->query('slot');
        $hashToken = hash_hmac('sha256', $session->id.'|'.$slot, config('app.key'));

        return view('asistencia.session', compact('session', 'slot', 'hashToken', 'publicToken'));
    }

    public function consultarAbogado(Request $request, $publicToken)
    {
        $session = $this->activeSession($publicToken);
        $data = $request->validate([
            'cc' => 'required|string|max:40',
            'slot' => 'required|integer',
            'token' => 'required|string',
        ]);

        if (!$this->hasValidFormToken($session, (int) $data['slot'], $data['token'])) {
            return response()->json([
                'message' => 'El formulario ya no es válido. Escanea nuevamente el código QR.',
            ], 419);
        }

        $abogados = $this->abogadosByCedula($data['cc']);
        if ($abogados->isEmpty()) {
            return response()->json([
                'message' => 'No encontramos una persona caracterizada con esa cédula.',
            ], 404);
        }

        if ($abogados->count() > 1) {
            return response()->json([
                'message' => 'Encontramos más de un registro con esta cédula. Solicita apoyo a coordinación para revisar la caracterización.',
            ], 409);
        }

        $abogado = $abogados->first();
        if (Control::where('asistencia_session_id', $session->id)
            ->where('codigo_abogado', (string) $abogado->id)
            ->exists()) {
            return response()->json([
                'message' => 'Esta persona ya registró asistencia en la reunión.',
            ], 409);
        }

        return response()->json([
            'nombre' => $abogado->nombre,
            'correo' => $abogado->correo,
            'telefono' => $abogado->telefono,
        ]);
    }

    public function procesarFormularioSesion(Request $request, $publicToken)
    {
        $session = $this->activeSession($publicToken);
        $data = $request->validate([
            'cc' => 'required|string|max:40',
            'correo' => 'required|email|max:255',
            'telefono' => 'required|string|max:50',
            'slot' => 'required|integer',
            'token' => 'required|string',
        ]);

        if (!$this->hasValidFormToken($session, (int) $data['slot'], $data['token'])) {
            return redirect()->back()
                ->withErrors(['error' => 'El formulario ya no es válido. Escanea nuevamente el código QR.'])
                ->withInput();
        }

        $cedula = $this->normalizeDigits($data['cc']);
        $correo = $this->normalizeEmail($data['correo']);
        $telefono = trim((string) $data['telefono']);

        if (!$this->isUsefulPhone($telefono)) {
            return redirect()->back()->withErrors([
                'telefono' => 'Ingresa un número de celular válido. No se permiten valores vacíos, N/D o No registra.',
            ])->withInput();
        }

        $result = DB::transaction(function () use ($session, $cedula, $correo, $telefono) {
            $abogados = $this->abogadosByCedula($cedula, true);
            if ($abogados->isEmpty()) {
                return ['error' => 'No encontramos una persona caracterizada con esa cédula.'];
            }
            if ($abogados->count() > 1) {
                return ['error' => 'Encontramos más de un registro con esta cédula. Solicita apoyo a coordinación.'];
            }

            $abogado = $abogados->first();
            $codigoAbogado = (string) $abogado->id;
            if (Control::where('asistencia_session_id', $session->id)
                ->where('codigo_abogado', $codigoAbogado)
                ->exists()) {
                return ['error' => 'Ya registraste asistencia en esta reunión.'];
            }

            $correoOcupado = Abogado::query()
                ->where('id', '<>', $abogado->id)
                ->whereRaw('LOWER(TRIM(correo)) = ?', [$correo])
                ->lockForUpdate()
                ->exists();
            if ($correoOcupado) {
                return ['error' => 'El correo ingresado ya pertenece a otra caracterización. Usa otro correo o solicita apoyo a coordinación.'];
            }

            $oldEmail = $abogado->correo;
            $oldPhone = $abogado->telefono;
            $emailChanged = $this->normalizeEmail($oldEmail) !== $correo;
            $phoneChanged = $this->normalizePhone($oldPhone) !== $this->normalizePhone($telefono);

            if ($emailChanged || $phoneChanged) {
                $abogado->correo = $correo;
                $abogado->telefono = $telefono;
                $abogado->save();

                AbogadoContactUpdate::create([
                    'abogado_id' => $abogado->id,
                    'reunion_id' => $session->reunion_id,
                    'asistencia_session_id' => $session->id,
                    'old_phone' => $oldPhone,
                    'new_phone' => $telefono,
                    'old_email' => $oldEmail,
                    'new_email' => $correo,
                    'source' => 'asistencia',
                ]);
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

            return ['contact_updated' => $emailChanged || $phoneChanged];
        });

        if (!empty($result['error'])) {
            return redirect()->back()->withErrors(['error' => $result['error']])->withInput();
        }

        $message = !empty($result['contact_updated'])
            ? 'Asistencia registrada y datos de contacto actualizados correctamente.'
            : 'Asistencia registrada con éxito.';

        return redirect()->back()
            ->with('info', $message)
            ->with('attendance_registered', true);
    }

    private function activeSession(string $publicToken): AsistenciaSession
    {
        return AsistenciaSession::with('reunion')
            ->where('public_token', $publicToken)
            ->where('activa', true)
            ->firstOrFail();
    }

    private function hasValidFormToken(AsistenciaSession $session, int $slot, string $token): bool
    {
        $expected = hash_hmac('sha256', $session->id.'|'.$slot, config('app.key'));
        return hash_equals($expected, $token);
    }

    private function abogadosByCedula(string $cedula, bool $lock = false)
    {
        $query = Abogado::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(cc, '.', ''), ',', ''), '-', ''), ' ', '') = ?",
                [$this->normalizeDigits($cedula)]
            )
            ->orderBy('id')
            ->limit(2);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function normalizeDigits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    private function normalizePhone(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        return preg_replace('/[\s\-\(\)\.]+/u', '', $value) ?? $value;
    }

    private function normalizeEmail(?string $value): string
    {
        return mb_strtolower(trim((string) $value), 'UTF-8');
    }

    private function isMissingPhone(?string $value): bool
    {
        $normalized = $this->normalizePlaceholder($value);
        return in_array($normalized, ['', 'n/d', 'nd', 'no registra'], true);
    }

    private function isUsefulPhone(?string $value): bool
    {
        return !$this->isMissingPhone($value);
    }

    private function normalizePlaceholder(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
