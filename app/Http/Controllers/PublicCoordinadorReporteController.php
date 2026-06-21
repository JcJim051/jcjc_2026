<?php

namespace App\Http\Controllers;

use App\Models\Abogado;
use App\Models\Candidato;
use App\Models\Eleccion;
use App\Models\MesaReporte;
use App\Models\MesaReporteAudit;
use App\Models\TerritorioToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicCoordinadorReporteController extends Controller
{
    public function identify(string $token)
    {
        $tokenRow = $this->getReportTokenOrFail($token, false);

        if (!$tokenRow->activo || !$this->tokenElectionIsActive($tokenRow) || ($tokenRow->expires_at && $tokenRow->expires_at->isPast())) {
            return view('public.coordinador_reportes.cerrado', ['token' => $tokenRow]);
        }

        return view('public.coordinador_reportes.identify', [
            'token' => $tokenRow,
            'eleccion' => Eleccion::find($tokenRow->eleccion_id),
        ]);
    }

    public function lookup(Request $request, string $token)
    {
        $tokenRow = $this->getReportTokenOrFail($token, false);
        if (!$tokenRow->activo || !$this->tokenElectionIsActive($tokenRow) || ($tokenRow->expires_at && $tokenRow->expires_at->isPast())) {
            return view('public.coordinador_reportes.cerrado', ['token' => $tokenRow]);
        }

        $data = $request->validate([
            'cedula' => ['required', 'string', 'max:50'],
        ]);

        $cedula = $this->normalizeCedula($data['cedula']);
        $puestos = $this->findCoordinatorPuestos($tokenRow, $cedula);

        if ($puestos->isEmpty()) {
            return back()->withErrors(['cedula' => 'No encontramos coordinaciones activas para esta cédula en la elección y territorio del link.'])->withInput();
        }

        $abogado = Abogado::whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(cc, '.', ''), ',', ''), '-', ''), ' ', '') = ?", [$cedula])->first();
        if (!$abogado) {
            return back()->withErrors(['cedula' => 'No encontramos el coordinador en la caracterización.'])->withInput();
        }

        $request->session()->put($this->sessionKey($tokenRow), [
            'abogado_id' => $abogado->id,
            'cedula' => $cedula,
            'puesto_ids' => $puestos->pluck('puesto_id')->unique()->values()->all(),
            'selected_puesto_id' => $puestos->count() === 1 ? (int) $puestos->first()->puesto_id : null,
        ]);

        if ($puestos->count() === 1) {
            return redirect()->route('public.coordinador_reportes.puesto', $tokenRow->token);
        }

        return view('public.coordinador_reportes.select_puesto', [
            'token' => $tokenRow,
            'eleccion' => Eleccion::find($tokenRow->eleccion_id),
            'abogado' => $abogado,
            'puestos' => $puestos,
        ]);
    }

    public function selectPuesto(Request $request, string $token)
    {
        $tokenRow = $this->getReportTokenOrFail($token);
        $auth = $this->getSessionAuth($request, $tokenRow);

        $data = $request->validate([
            'puesto_id' => ['required', 'integer'],
        ]);

        if (!in_array((int) $data['puesto_id'], array_map('intval', $auth['puesto_ids'] ?? []), true)) {
            abort(403);
        }

        $auth['selected_puesto_id'] = (int) $data['puesto_id'];
        $request->session()->put($this->sessionKey($tokenRow), $auth);

        return redirect()->route('public.coordinador_reportes.puesto', $tokenRow->token);
    }

    public function puesto(Request $request, string $token)
    {
        $tokenRow = $this->getReportTokenOrFail($token);
        $auth = $this->getSessionAuth($request, $tokenRow);
        $puestoId = (int) ($auth['selected_puesto_id'] ?? 0);
        if ($puestoId <= 0) {
            return redirect()->route('public.coordinador_reportes.identify', $tokenRow->token);
        }

        $puesto = DB::table('eleccion_puestos')
            ->where('id', $puestoId)
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->first();
        if (!$puesto) {
            abort(404);
        }

        $mesas = DB::table('eleccion_mesas as em')
            ->leftJoin('mesa_reportes as mr', 'mr.eleccion_mesa_id', '=', 'em.id')
            ->where('em.eleccion_id', $tokenRow->eleccion_id)
            ->where('em.eleccion_puesto_id', $puestoId)
            ->orderBy('em.mesa_num')
            ->get([
                'em.id', 'em.mesa_num', 'mr.afluencia_9', 'mr.afluencia_11', 'mr.afluencia_14',
                'mr.e14_reportado_at', 'mr.control_final_at', 'mr.reconteo', 'mr.reclamacion', 'mr.jurados_firmaron',
            ])
            ->map(function ($mesa) {
                $mesa->afluencia_completa = !is_null($mesa->afluencia_9) && !is_null($mesa->afluencia_11) && !is_null($mesa->afluencia_14);
                return $mesa;
            });

        $abogado = Abogado::find($auth['abogado_id']);

        return view('public.coordinador_reportes.puesto', [
            'token' => $tokenRow,
            'eleccion' => Eleccion::find($tokenRow->eleccion_id),
            'abogado' => $abogado,
            'puesto' => $puesto,
            'mesas' => $mesas,
        ]);
    }

    public function mesa(Request $request, string $token, int $mesa)
    {
        return redirect()->route('public.coordinador_reportes.mesa_afluencia', [$token, $mesa]);
    }

    public function mesaAfluencia(Request $request, string $token, int $mesa)
    {
        $context = $this->buildMesaViewContext($request, $token, $mesa);

        return view('public.coordinador_reportes.mesa_afluencia', $context);
    }

    public function mesaE14(Request $request, string $token, int $mesa)
    {
        $context = $this->buildMesaViewContext($request, $token, $mesa);

        return view('public.coordinador_reportes.mesa_e14', $context);
    }

    public function saveAfluencia(Request $request, string $token, int $mesa)
    {
        $tokenRow = $this->getReportTokenOrFail($token);
        $auth = $this->getSessionAuth($request, $tokenRow);
        $mesaRow = $this->getMesaAuthorized($tokenRow, $auth, $mesa);

        $data = $request->validate([
            'afluencia_9' => ['nullable', 'integer', 'min:0'],
            'afluencia_11' => ['nullable', 'integer', 'min:0'],
            'afluencia_14' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $mesaRow, $tokenRow, $auth) {
            $reporte = MesaReporte::query()->lockForUpdate()->firstOrNew([
                'eleccion_mesa_id' => $mesaRow->mesa_id,
            ]);
            $before = $reporte->exists ? $reporte->toArray() : null;

            $reporte->fill([
                'eleccion_id' => $tokenRow->eleccion_id,
                'territorio_token_id' => $tokenRow->id,
                'eleccion_puesto_id' => $mesaRow->puesto_id,
                'coordinacion_id' => $mesaRow->coordinacion_id,
                'abogado_id' => $auth['abogado_id'],
                'afluencia_9' => $data['afluencia_9'] ?? null,
                'afluencia_11' => $data['afluencia_11'] ?? null,
                'afluencia_14' => $data['afluencia_14'] ?? null,
                'created_by_abogado_id' => $reporte->created_by_abogado_id ?: $auth['abogado_id'],
                'updated_by_abogado_id' => $auth['abogado_id'],
            ]);
            $reporte->save();

            $this->logAudit($reporte, $auth['abogado_id'], $reporte->wasRecentlyCreated ? 'crear_afluencia' : 'actualizar_afluencia', $before, $reporte->fresh()->toArray());
        });

        return back()->with('success', 'Afluencia guardada correctamente.');
    }

    public function saveE14(Request $request, string $token, int $mesa)
    {
        $tokenRow = $this->getReportTokenOrFail($token);
        $auth = $this->getSessionAuth($request, $tokenRow);
        $mesaRow = $this->getMesaAuthorized($tokenRow, $auth, $mesa);

        $candidatos = Candidato::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('activo', 1)
            ->whereNotNull('campo_e14')
            ->orderBy('campo_e14')
            ->orderBy('codigo')
            ->get();

        $rules = [
            'censo_mesa' => ['nullable', 'integer', 'min:0'],
            'votos_en_urna' => ['nullable', 'integer', 'min:0'],
            'votos_incinerados' => ['nullable', 'integer', 'min:0'],
            'votos_blanco' => ['required', 'integer', 'min:0'],
            'votos_nulos' => ['required', 'integer', 'min:0'],
            'votos_no_marcados' => ['required', 'integer', 'min:0'],
        ];
        foreach ($candidatos as $candidato) {
            $rules['candidato_' . $candidato->id] = ['required', 'integer', 'min:0'];
        }

        $data = $request->validate($rules);
        $detalle = [];
        foreach ($candidatos as $candidato) {
            $detalle[] = [
                'candidato_id' => $candidato->id,
                'codigo' => (string) $candidato->codigo,
                'nombre' => (string) $candidato->nombre,
                'campo_e14' => (int) $candidato->campo_e14,
                'votos' => (int) $data['candidato_' . $candidato->id],
            ];
        }

        DB::transaction(function () use ($data, $detalle, $mesaRow, $tokenRow, $auth) {
            $reporte = MesaReporte::query()->lockForUpdate()->firstOrNew([
                'eleccion_mesa_id' => $mesaRow->mesa_id,
            ]);
            $before = $reporte->exists ? $reporte->toArray() : null;
            $reporte->fill([
                'eleccion_id' => $tokenRow->eleccion_id,
                'territorio_token_id' => $tokenRow->id,
                'eleccion_puesto_id' => $mesaRow->puesto_id,
                'coordinacion_id' => $mesaRow->coordinacion_id,
                'abogado_id' => $auth['abogado_id'],
                'censo_mesa' => $data['censo_mesa'] ?? null,
                'votos_en_urna' => $data['votos_en_urna'] ?? null,
                'votos_incinerados' => $data['votos_incinerados'] ?? null,
                'votos_blanco' => (int) $data['votos_blanco'],
                'votos_nulos' => (int) $data['votos_nulos'],
                'votos_no_marcados' => (int) $data['votos_no_marcados'],
                'e14_detalle' => $detalle,
                'e14_reportado_at' => Carbon::now('America/Bogota'),
                'created_by_abogado_id' => $reporte->created_by_abogado_id ?: $auth['abogado_id'],
                'updated_by_abogado_id' => $auth['abogado_id'],
            ]);
            $reporte->save();
            $this->logAudit($reporte, $auth['abogado_id'], $reporte->wasRecentlyCreated ? 'crear_e14' : 'actualizar_e14', $before, $reporte->fresh()->toArray());
        });

        return back()->with('success', 'Datos E14 guardados correctamente. Ahora puedes subir la foto del acta.');
    }

    public function saveE14Foto(Request $request, string $token, int $mesa)
    {
        $tokenRow = $this->getReportTokenOrFail($token);
        $auth = $this->getSessionAuth($request, $tokenRow);
        $mesaRow = $this->getMesaAuthorized($tokenRow, $auth, $mesa);
        $reporte = MesaReporte::query()->where('eleccion_mesa_id', $mesaRow->mesa_id)->first();

        if (!$reporte || !$reporte->e14_reportado_at) {
            return back()->withErrors(['e14_foto' => 'Primero debes guardar los datos E14 antes de subir la foto.']);
        }

        if (!$reporte->control_final_at) {
            return back()->withErrors(['e14_foto' => 'Primero debes completar el control final de la mesa antes de subir la foto del E14.']);
        }

        $data = $request->validate([
            'e14_foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], $this->publicImageMessages('foto del E14'));

        DB::transaction(function () use ($data, $reporte, $auth) {
            $reporte = MesaReporte::query()->lockForUpdate()->findOrFail($reporte->id);
            $before = $reporte->toArray();

            if ($reporte->e14_foto_path) {
                Storage::disk('public')->delete($reporte->e14_foto_path);
            }

            $reporte->e14_foto_path = $data['e14_foto']->store('mesa-reportes/e14', 'public');
            $reporte->updated_by_abogado_id = $auth['abogado_id'];
            $reporte->save();

            $this->logAudit($reporte, $auth['abogado_id'], 'actualizar_e14_foto', $before, $reporte->fresh()->toArray());
        });

        return back()->with('success', 'Foto del E14 cargada correctamente.');
    }

    public function saveControl(Request $request, string $token, int $mesa)
    {
        $tokenRow = $this->getReportTokenOrFail($token);
        $auth = $this->getSessionAuth($request, $tokenRow);
        $mesaRow = $this->getMesaAuthorized($tokenRow, $auth, $mesa);
        $reporte = MesaReporte::query()->where('eleccion_mesa_id', $mesaRow->mesa_id)->first();

        if (!$reporte || !$reporte->e14_reportado_at) {
            return back()->withErrors(['control' => 'Primero debes guardar los datos E14 de esta mesa.']);
        }

        $data = $request->validate([
            'reconteo' => ['required', 'in:0,1'],
            'reclamacion' => ['required', 'in:0,1'],
            'jurados_firmaron' => ['required', 'integer', 'min:0'],
            'reclamacion_origen' => ['nullable', 'in:nuestra,otro'],
            'reclamacion_candidato_id' => ['nullable', 'integer', 'exists:candidatos,id'],
            'reclamacion_comentario' => ['nullable', 'string'],
            'reclamacion_foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], $this->publicImageMessages('foto de reclamación'));

        if ((int) $data['reclamacion'] === 1) {
            if (empty($data['reclamacion_origen'])) {
                return back()->withErrors(['reclamacion_origen' => 'Debes indicar si la reclamación es nuestra o de otro candidato.'])->withInput();
            }
            if (trim((string) ($data['reclamacion_comentario'] ?? '')) === '') {
                return back()->withErrors(['reclamacion_comentario' => 'Debes registrar el comentario del testigo sobre la reclamación.'])->withInput();
            }
            if (($data['reclamacion_origen'] ?? null) === 'otro' && empty($data['reclamacion_candidato_id'])) {
                return back()->withErrors(['reclamacion_candidato_id' => 'Debes seleccionar el candidato relacionado con la reclamación.'])->withInput();
            }
        }

        DB::transaction(function () use ($request, $data, $reporte, $auth) {
            $reporte = MesaReporte::query()->lockForUpdate()->findOrFail($reporte->id);
            $before = $reporte->toArray();

            $reporte->reconteo = (bool) ((int) $data['reconteo']);
            $reporte->reclamacion = (bool) ((int) $data['reclamacion']);
            $reporte->jurados_firmaron = (int) $data['jurados_firmaron'];
            $reporte->reclamacion_origen = (int) $data['reclamacion'] === 1 ? ($data['reclamacion_origen'] ?? null) : null;
            $reporte->reclamacion_candidato_id = (int) $data['reclamacion'] === 1 && ($data['reclamacion_origen'] ?? null) === 'otro'
                ? (int) ($data['reclamacion_candidato_id'] ?? 0) ?: null
                : null;
            $reporte->reclamacion_comentario = (int) $data['reclamacion'] === 1
                ? trim((string) ($data['reclamacion_comentario'] ?? ''))
                : null;
            $reporte->control_final_at = Carbon::now('America/Bogota');
            $reporte->updated_by_abogado_id = $auth['abogado_id'];

            if ($request->hasFile('reclamacion_foto')) {
                if ($reporte->reclamacion_foto_path) {
                    Storage::disk('public')->delete($reporte->reclamacion_foto_path);
                }
                $reporte->reclamacion_foto_path = $request->file('reclamacion_foto')->store('reclamaciones', 'public');
            }

            if ((int) $data['reclamacion'] !== 1 && $reporte->reclamacion_foto_path) {
                Storage::disk('public')->delete($reporte->reclamacion_foto_path);
                $reporte->reclamacion_foto_path = null;
            }

            $reporte->save();
            $this->logAudit($reporte, $auth['abogado_id'], 'control_final', $before, $reporte->fresh()->toArray());
        });

        return back()->with('success', 'Control final guardado correctamente.');
    }

    private function getMesaAuthorized(TerritorioToken $tokenRow, array $auth, int $mesaId)
    {
        $puestoId = (int) ($auth['selected_puesto_id'] ?? 0);
        if ($puestoId <= 0) {
            abort(403);
        }

        $mesa = DB::table('eleccion_mesas as em')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'em.eleccion_puesto_id')
            ->where('em.id', $mesaId)
            ->where('em.eleccion_id', $tokenRow->eleccion_id)
            ->where('em.eleccion_puesto_id', $puestoId)
            ->selectRaw('em.id as mesa_id, em.mesa_num, ep.id as puesto_id, ep.puesto, ep.municipio, ep.comuna, ep.dd, ep.mm, ep.zz, ep.pp')
            ->first();

        if (!$mesa) {
            abort(404);
        }

        if (!$this->rowWithinTokenGeo($tokenRow, $mesa->dd, $mesa->mm, $mesa->comuna)) {
            abort(403);
        }

        $coordinacionId = DB::table('abogado_coordinaciones as ac')
            ->leftJoin('eleccion_mesas as em', 'em.id', '=', 'ac.eleccion_mesa_id')
            ->leftJoin('eleccion_puestos as ep_cod', function ($join) {
                $join->on('ep_cod.eleccion_id', '=', 'ac.eleccion_id')
                    ->where(function ($query) {
                        $query->whereColumn('ep_cod.codigo_puesto', 'ac.codpuesto')
                            ->orWhereRaw('CONCAT(ep_cod.dd, ep_cod.mm, ep_cod.zz, ep_cod.pp) = ac.codpuesto')
                            ->orWhere(function ($shortCode) {
                                $shortCode->whereRaw('CHAR_LENGTH(ac.codpuesto) <= 2')
                                    ->whereColumn('ep_cod.pp', 'ac.codpuesto');
                            });
                    });
            })
            ->leftJoin('eleccion_puestos as ep_mesa', 'ep_mesa.id', '=', 'em.eleccion_puesto_id')
            ->where('ac.eleccion_id', $tokenRow->eleccion_id)
            ->whereNull('ac.released_at')
            ->where('ac.abogado_id', $auth['abogado_id'])
            ->whereRaw('COALESCE(ep_mesa.id, ep_cod.id) = ?', [$puestoId])
            ->orderBy('ac.id')
            ->value('ac.id');

        $mesa->coordinacion_id = $coordinacionId;
        return $mesa;
    }

    private function findCoordinatorPuestos(TerritorioToken $tokenRow, string $cedula)
    {
        return DB::table('abogado_coordinaciones as ac')
            ->join('abogados as a', 'a.id', '=', 'ac.abogado_id')
            ->leftJoin('eleccion_mesas as em', 'em.id', '=', 'ac.eleccion_mesa_id')
            ->leftJoin('eleccion_puestos as ep_cod', function ($join) {
                $join->on('ep_cod.eleccion_id', '=', 'ac.eleccion_id')
                    ->where(function ($query) {
                        $query->whereColumn('ep_cod.codigo_puesto', 'ac.codpuesto')
                            ->orWhereRaw('CONCAT(ep_cod.dd, ep_cod.mm, ep_cod.zz, ep_cod.pp) = ac.codpuesto')
                            ->orWhere(function ($shortCode) {
                                $shortCode->whereRaw('CHAR_LENGTH(ac.codpuesto) <= 2')
                                    ->whereColumn('ep_cod.pp', 'ac.codpuesto');
                            });
                    });
            })
            ->leftJoin('eleccion_puestos as ep_mesa', 'ep_mesa.id', '=', 'em.eleccion_puesto_id')
            ->where('ac.eleccion_id', $tokenRow->eleccion_id)
            ->whereNull('ac.released_at')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(a.cc, '.', ''), ',', ''), '-', ''), ' ', '') = ?", [$cedula])
            ->orderBy('a.nombre')
            ->get([
                'ac.id as coordinacion_id', 'a.id as abogado_id', 'a.nombre as abogado_nombre', 'a.cc as abogado_cc',
                DB::raw('COALESCE(ep_mesa.id, ep_cod.id) as puesto_id'),
                DB::raw('COALESCE(ep_mesa.puesto, ep_cod.puesto) as puesto'),
                DB::raw('COALESCE(ep_mesa.municipio, ep_cod.municipio) as municipio'),
                DB::raw('COALESCE(ep_mesa.comuna, ep_cod.comuna) as comuna'),
                DB::raw('COALESCE(ep_mesa.dd, ep_cod.dd) as dd'),
                DB::raw('COALESCE(ep_mesa.mm, ep_cod.mm) as mm'),
            ])
            ->filter(function ($row) use ($tokenRow) {
                return !empty($row->puesto_id) && $this->rowWithinTokenGeo($tokenRow, (string) $row->dd, (string) $row->mm, $row->comuna);
            })
            ->groupBy('puesto_id')
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'puesto_id' => (int) $first->puesto_id,
                    'puesto' => (string) ($first->puesto ?? 'N/D'),
                    'municipio' => (string) ($first->municipio ?? 'N/D'),
                    'comuna' => (string) ($first->comuna ?? 'N/D'),
                    'coordinaciones' => $group->pluck('coordinacion_id')->values()->all(),
                ];
            })
            ->sortBy([['municipio', 'asc'], ['puesto', 'asc']])
            ->values();
    }

    private function publicImageMessages(string $label): array
    {
        return [
            'mimes' => 'Formato no válido para la ' . $label . '. Usa JPG, JPEG, PNG o WEBP. Si tomaste la foto desde iPhone y te aparece HEIC/HEIF, envíala por WhatsApp como imagen, descárgala y vuelve a subirla. No la envíes como documento.',
            'max' => 'La ' . $label . ' supera el tamaño máximo permitido de 8 MB. Si estás en iPhone, una opción rápida es enviarla por WhatsApp como imagen, descargarla y subir esa versión.',
            'required' => 'Debes seleccionar la ' . $label . '.',
            'file' => 'Debes adjuntar un archivo válido para la ' . $label . '.',
        ];
    }

    private function getReportTokenOrFail(string $token, bool $requireActive = true): TerritorioToken
    {
        $tokenRow = TerritorioToken::where('token', $token)->first();
        if (!$tokenRow) {
            abort(404);
        }
        $modulo = $tokenRow->modulo ?: ($tokenRow->es_consulta ? 'consulta' : 'referidos');
        if ($modulo !== 'reporte_operativo') {
            abort(404);
        }
        if ($requireActive && !$tokenRow->activo) {
            abort(404);
        }
        if ($tokenRow->expires_at && $tokenRow->expires_at->isPast() && $requireActive) {
            abort(404);
        }
        return $tokenRow;
    }

    private function tokenElectionIsActive(TerritorioToken $tokenRow): bool
    {
        return Eleccion::where('id', $tokenRow->eleccion_id)->where('estado', 'activa')->exists();
    }

    private function getSessionAuth(Request $request, TerritorioToken $tokenRow): array
    {
        $auth = (array) $request->session()->get($this->sessionKey($tokenRow), []);
        if (empty($auth['abogado_id'])) {
            abort(403);
        }
        return $auth;
    }

    private function sessionKey(TerritorioToken $tokenRow): string
    {
        return 'coordinador_reportes.' . $tokenRow->token;
    }

    private function buildMesaViewContext(Request $request, string $token, int $mesa): array
    {
        $tokenRow = $this->getReportTokenOrFail($token);
        $auth = $this->getSessionAuth($request, $tokenRow);
        $mesaRow = $this->getMesaAuthorized($tokenRow, $auth, $mesa);

        $reporte = MesaReporte::query()->where('eleccion_mesa_id', $mesaRow->mesa_id)->first();
        $candidatos = Candidato::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('activo', 1)
            ->whereNotNull('campo_e14')
            ->orderBy('campo_e14')
            ->orderBy('codigo')
            ->get();

        return [
            'token' => $tokenRow,
            'eleccion' => Eleccion::find($tokenRow->eleccion_id),
            'abogado' => Abogado::find($auth['abogado_id']),
            'mesa' => $mesaRow,
            'reporte' => $reporte,
            'candidatos' => $candidatos,
        ];
    }

    private function logAudit(MesaReporte $reporte, int $abogadoId, string $accion, $before, $after): void
    {
        MesaReporteAudit::create([
            'mesa_reporte_id' => $reporte->id,
            'abogado_id' => $abogadoId,
            'accion' => $accion,
            'before_payload' => $before,
            'after_payload' => $after,
        ]);
    }

    private function normalizeCedula(?string $value): string
    {
        return preg_replace('/[\s.,-]+/', '', trim((string) $value)) ?? '';
    }

    private function parseComunasToken(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        return collect(explode(',', $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function tokenMunicipioCodes(TerritorioToken $tokenRow): array
    {
        $items = collect(is_array($tokenRow->municipios) ? $tokenRow->municipios : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && strpos($value, '-') !== false)
            ->unique()
            ->values();

        if ($items->isEmpty() && $tokenRow->dd && $tokenRow->mm) {
            $items = collect([$tokenRow->dd . '-' . $tokenRow->mm]);
        }

        return $items->all();
    }

    private function rowWithinTokenGeo(TerritorioToken $tokenRow, string $dd, string $mm, ?string $comuna = null): bool
    {
        $geoCodes = $this->tokenMunicipioCodes($tokenRow);
        if (!in_array(trim($dd) . '-' . trim($mm), $geoCodes, true)) {
            return false;
        }
        $comunas = $this->parseComunasToken($tokenRow->comuna);
        if (!empty($comunas)) {
            return in_array(trim((string) $comuna), $comunas, true);
        }
        return true;
    }
}
