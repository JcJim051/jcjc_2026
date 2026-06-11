<?php

namespace App\Http\Controllers;

use App\Models\Abogado;
use App\Models\AbogadoAccessToken;
use App\Models\Puestos;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicAbogadoProfileController extends Controller
{
    public function projection(string $token)
    {
        $accessToken = AbogadoAccessToken::where('token', $token)->firstOrFail();
        $svg = (new Writer(
            new ImageRenderer(new RendererStyle(760, 3), new SvgImageBackEnd())
        ))->writeString($accessToken->publicUrl());
        $qrSvg = trim(substr($svg, strpos($svg, "\n") + 1));

        return view('admin.abogado_tokens.projection', [
            'token' => $accessToken,
            'qrSvg' => $qrSvg,
        ]);
    }

    public function characterizationForm(string $token)
    {
        $accessToken = $this->resolveToken($token, AbogadoAccessToken::TYPE_CHARACTERIZATION);

        return view('public.abogados.characterization', [
            'accessToken' => $accessToken,
            'puestos' => $this->puestos(),
        ]);
    }

    public function characterizationStore(Request $request, string $token)
    {
        $accessToken = $this->resolveToken($token, AbogadoAccessToken::TYPE_CHARACTERIZATION);
        $data = $this->validateProfile($request, true);
        $data['cc'] = $this->normalizeCedula($data['cc']);

        if ($this->abogadoByCedula($data['cc'])) {
            return back()
                ->withErrors(['cc' => 'La cédula ya está registrada. Utiliza el enlace de actualización de datos.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $data, $accessToken) {
            $lockedToken = AbogadoAccessToken::query()
                ->lockForUpdate()
                ->findOrFail($accessToken->id);

            abort_unless($lockedToken->isAvailable(), 410, 'El enlace venció, fue utilizado o está desactivado.');

            $data = $this->storeFiles($request, $data);
            $data['activo'] = true;
            Abogado::create($data);

            $lockedToken->update([
                'used_at' => now(),
            ]);
            $lockedToken->increment('completed_count');
        });

        return view('public.abogados.success', [
            'title' => 'Caracterización completada',
            'message' => 'Tus datos fueron registrados y ahora haces parte del equipo.',
        ]);
    }

    public function updateIdentify(string $token)
    {
        $accessToken = $this->resolveToken($token, AbogadoAccessToken::TYPE_UPDATE);

        return view('public.abogados.identify', compact('accessToken'));
    }

    public function updateLookup(Request $request, string $token)
    {
        $accessToken = $this->resolveToken($token, AbogadoAccessToken::TYPE_UPDATE);
        $data = $request->validate([
            'cc' => 'required|string|max:50',
        ]);

        $abogado = $this->abogadoByCedula($data['cc']);
        if (!$abogado) {
            return back()->withErrors([
                'cc' => 'No encontramos un integrante registrado con esa cédula.',
            ])->withInput();
        }

        $request->session()->put($this->authorizationKey($accessToken), $abogado->id);

        return redirect()->route('public.abogados.update.form', $accessToken->token);
    }

    public function updateForm(Request $request, string $token)
    {
        $accessToken = $this->resolveToken($token, AbogadoAccessToken::TYPE_UPDATE);
        $abogado = $this->authorizedAbogado($request, $accessToken);

        return view('public.abogados.update', [
            'accessToken' => $accessToken,
            'abogado' => $abogado,
            'puestos' => $this->puestos(),
        ]);
    }

    public function updateStore(Request $request, string $token)
    {
        $accessToken = $this->resolveToken($token, AbogadoAccessToken::TYPE_UPDATE);
        $abogado = $this->authorizedAbogado($request, $accessToken);
        $data = $this->validateProfile($request, false);
        $data = $this->storeFiles($request, $data, $abogado);

        $abogado->update($data);
        $accessToken->increment('completed_count');
        $request->session()->forget($this->authorizationKey($accessToken));

        return view('public.abogados.success', [
            'title' => 'Información actualizada',
            'message' => 'Tus datos fueron actualizados correctamente.',
        ]);
    }

    private function resolveToken(string $token, string $type): AbogadoAccessToken
    {
        $accessToken = AbogadoAccessToken::where('token', $token)
            ->where('type', $type)
            ->firstOrFail();

        if (!$accessToken->isAvailable()) {
            abort(410, 'El enlace venció, fue utilizado o está desactivado.');
        }

        return $accessToken;
    }

    private function authorizedAbogado(Request $request, AbogadoAccessToken $token): Abogado
    {
        $abogadoId = (int) $request->session()->get($this->authorizationKey($token), 0);
        abort_unless($abogadoId > 0, 403);

        return Abogado::findOrFail($abogadoId);
    }

    private function authorizationKey(AbogadoAccessToken $token): string
    {
        return 'abogado_update_authorized_' . $token->id;
    }

    private function normalizeCedula(string $cedula): string
    {
        return preg_replace('/\D+/', '', $cedula) ?: trim($cedula);
    }

    private function abogadoByCedula(string $cedula): ?Abogado
    {
        $normalized = $this->normalizeCedula($cedula);

        return Abogado::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(cc, '.', ''), ',', ''), '-', ''), ' ', '') = ?",
                [$normalized]
            )
            ->first();
    }

    private function validateProfile(Request $request, bool $creating): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'cc' => ($creating ? 'required' : 'sometimes') . '|string|max:50',
            'correo' => 'required|email|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:50',
            'comuna' => 'required|string|max:255',
            'puesto' => 'required|string|max:255',
            'mesa' => 'required|string|max:255',
            'estudios' => 'nullable|string|max:255',
            'titulo' => 'nullable|string|max:255',
            'entidad' => 'nullable|string|max:255',
            'secretaria' => 'nullable|string|max:255',
            'honorarios' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:4096',
            'pdf_cc' => 'nullable|file|mimes:pdf|max:4096',
        ]);
    }

    private function storeFiles(Request $request, array $data, ?Abogado $abogado = null): array
    {
        if ($request->hasFile('foto')) {
            if ($abogado && $abogado->foto) {
                Storage::disk('public')->delete($abogado->foto);
            }
            $data['foto'] = $request->file('foto')->store('abogados/fotos', 'public');
        }

        if ($request->hasFile('pdf_cc')) {
            if ($abogado && $abogado->pdf_cc) {
                Storage::disk('public')->delete($abogado->pdf_cc);
            }
            $data['pdf_cc'] = $request->file('pdf_cc')->store('abogados/cc', 'public');
        }

        if ($abogado) {
            unset($data['cc']);
        }

        return $data;
    }

    private function puestos()
    {
        return Puestos::query()
            ->orderBy('mun')
            ->orderBy('nombre')
            ->get(['mun', 'nombre'])
            ->map(fn ($puesto) => trim(($puesto->mun ?? '') . ' - ' . ($puesto->nombre ?? ''), ' -'))
            ->filter()
            ->unique()
            ->values();
    }
}
