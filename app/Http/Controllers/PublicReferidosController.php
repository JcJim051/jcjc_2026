<?php

namespace App\Http\Controllers;

use App\Models\Eleccion;
use App\Models\EleccionPuesto;
use App\Models\MesaBloqueo;
use App\Models\Persona;
use App\Models\Referido;
use App\Models\TerritorioToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicReferidosController extends Controller
{
    public function form(string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        if ($tokenRow->es_consulta) {
            return redirect()->route('public.referidos.seguimiento', $tokenRow->token);
        }
        if (!$tokenRow->activo || ($tokenRow->expires_at && $tokenRow->expires_at->isPast())) {
            return view('public.referidos.cerrado', ['token' => $tokenRow]);
        }

        $departamento = EleccionPuesto::where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->select('departamento')
            ->distinct()
            ->first();

        $municipio = EleccionPuesto::where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->select('municipio')
            ->distinct()
            ->first();

        return view('public.referidos.form', [
            'token' => $tokenRow,
            'departamento' => $departamento?->departamento,
            'municipio' => $municipio?->municipio,
            'comuna' => $tokenRow->comuna,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        if ($tokenRow->es_consulta) {
            return redirect()->route('public.referidos.seguimiento', $tokenRow->token)
                ->with('error', 'Este token es solo de consulta.');
        }
        if (!$tokenRow->activo || ($tokenRow->expires_at && $tokenRow->expires_at->isPast())) {
            return redirect()->route('public.referidos.seguimiento', $tokenRow->token)
                ->with('error', 'Formulario cerrado. Solo seguimiento disponible.');
        }

        $request->merge([
            'cedula' => trim((string) $request->input('cedula')),
            'email' => mb_strtolower(trim((string) $request->input('email')), 'UTF-8'),
            'nombre' => trim((string) $request->input('nombre')),
            'telefono' => trim((string) $request->input('telefono')),
        ]);

        $data = $request->validate([
            'cedula' => ['required', 'string', 'max:50', Rule::unique('personas', 'cedula')],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('personas', 'email')],
            'telefono' => ['required', 'string', 'max:50'],
            'puesto_id' => ['required', 'integer', 'exists:eleccion_puestos,id'],
            'mesa_num' => ['required', 'integer', 'min:1'],
            'cedula_pdf' => ['required', 'file', 'mimes:pdf', 'max:2048'],
        ]);

        $puesto = EleccionPuesto::where('id', $data['puesto_id'])
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->first();

        if (!$puesto) {
            return back()->withErrors(['puesto_id' => 'Puesto no permitido para este territorio.']);
        }

        if ($tokenRow->comuna && $puesto->comuna !== $tokenRow->comuna) {
            return back()->withErrors(['puesto_id' => 'Puesto no permitido para la comuna del territorio.']);
        }

        if ((int) $data['mesa_num'] > (int) $puesto->mesas_total) {
            return back()->withErrors(['mesa_num' => 'La mesa excede el total permitido para este puesto.']);
        }

        $mesaBloqueada = MesaBloqueo::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('eleccion_puesto_id', $puesto->id)
            ->where('mesa_num', (int) $data['mesa_num'])
            ->exists();
        if ($mesaBloqueada) {
            return back()->withErrors(['mesa_num' => 'La mesa seleccionada está bloqueada por asignación externa.'])->withInput();
        }

        $cupo = $this->getCupoMetrics((int) $tokenRow->eleccion_id, (int) $puesto->id);
        if ((int) $cupo['espacios_40_libres'] <= 0) {
            return back()->withErrors(['puesto_id' => 'Este puesto ya alcanzo su meta de cobertura para esta eleccion.'])->withInput();
        }

        $mesaOcupada = Referido::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('eleccion_puesto_id', $puesto->id)
            ->where('mesa_num', (int) $data['mesa_num'])
            ->where('estado', '<>', 'rechazado')
            ->exists();
        if ($mesaOcupada) {
            return back()->withErrors(['mesa_num' => 'La mesa seleccionada ya no esta disponible.'])->withInput();
        }

        $normalized = $this->normalizeName($data['nombre']);

        $pdfPath = $request->file('cedula_pdf')->store('cedulas', 'public');

        DB::beginTransaction();
        try {
            $personaId = Persona::insertGetId([
                'cedula' => $data['cedula'],
                'nombre' => $data['nombre'],
                'nombre_original' => $data['nombre'],
                'nombre_normalizado' => $normalized,
                'email' => $data['email'],
                'telefono' => $data['telefono'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Referido::create([
                'persona_id' => $personaId,
                'eleccion_id' => $tokenRow->eleccion_id,
                'eleccion_puesto_id' => $puesto->id,
                'territorio_token_id' => $tokenRow->id,
                'mesa_num' => (int) $data['mesa_num'],
                'cedula_pdf_path' => $pdfPath,
                'estado' => 'referido',
                'observaciones' => null,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Error al guardar: ' . $e->getMessage()]);
        }

        return redirect()->route('public.referidos.form', $token)
            ->with('success', 'Referido registrado correctamente.');
    }

    public function seguimiento(string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        $metaPct = $this->getMetaPctByEleccion((int) $tokenRow->eleccion_id);
        $municipiosConsulta = collect($tokenRow->municipios ?? [])->filter()->values();

        $municipio = null;
        if (!$tokenRow->es_consulta) {
            $municipio = EleccionPuesto::where('eleccion_id', $tokenRow->eleccion_id)
                ->where('dd', $tokenRow->dd)
                ->where('mm', $tokenRow->mm)
                ->select('municipio')
                ->distinct()
                ->first();
        }

        $referidos = DB::table('referidos as r')
            ->join('personas as p', 'p.id', '=', 'r.persona_id')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'r.eleccion_puesto_id')
            ->where(function ($q) use ($tokenRow, $municipiosConsulta) {
                if ($tokenRow->es_consulta) {
                    $q->where('r.eleccion_id', $tokenRow->eleccion_id)
                        ->where(function ($geo) use ($municipiosConsulta) {
                            foreach ($municipiosConsulta as $geoCode) {
                                if (strpos((string) $geoCode, '-') === false) {
                                    continue;
                                }
                                [$dd, $mm] = explode('-', (string) $geoCode);
                                $geo->orWhere(function ($g) use ($dd, $mm) {
                                    $g->where('ep.dd', $dd)->where('ep.mm', $mm);
                                });
                            }
                        });
                } else {
                    $q->where('r.territorio_token_id', $tokenRow->id);
                }
            })
            ->orderByDesc('r.id')
            ->select([
                'r.id',
                'p.cedula',
                'p.nombre',
                'p.email',
                'p.telefono',
                'ep.puesto',
                'ep.municipio',
                'r.mesa_num',
                'r.estado',
                'r.observaciones',
                'r.created_at',
            ])
            ->get();

        $puestosAlcance = EleccionPuesto::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where(function ($q) use ($tokenRow, $municipiosConsulta) {
                if ($tokenRow->es_consulta) {
                    foreach ($municipiosConsulta as $geoCode) {
                        if (strpos((string) $geoCode, '-') === false) {
                            continue;
                        }
                        [$dd, $mm] = explode('-', (string) $geoCode);
                        $q->orWhere(function ($g) use ($dd, $mm) {
                            $g->where('dd', $dd)->where('mm', $mm);
                        });
                    }
                } else {
                    $q->where('dd', $tokenRow->dd)->where('mm', $tokenRow->mm);
                }
            })
            ->when(!empty($tokenRow->comuna), function ($q) use ($tokenRow) {
                $q->where('comuna', $tokenRow->comuna);
            })
            ->orderBy('municipio')
            ->orderBy('puesto')
            ->get([
                'id',
                'municipio',
                'puesto',
                'mesas_total',
            ]);

        $referidosAggPorPuesto = DB::table('referidos')
            ->where(function ($q) use ($tokenRow, $municipiosConsulta) {
                if ($tokenRow->es_consulta) {
                    $q->where('eleccion_id', $tokenRow->eleccion_id)
                        ->whereIn('eleccion_puesto_id', EleccionPuesto::query()
                            ->where('eleccion_id', $tokenRow->eleccion_id)
                            ->where(function ($geoQ) use ($municipiosConsulta) {
                                foreach ($municipiosConsulta as $geoCode) {
                                    if (strpos((string) $geoCode, '-') === false) {
                                        continue;
                                    }
                                    [$dd, $mm] = explode('-', (string) $geoCode);
                                    $geoQ->orWhere(function ($g) use ($dd, $mm) {
                                        $g->where('dd', $dd)->where('mm', $mm);
                                    });
                                }
                            })
                            ->pluck('id')
                            ->all());
                } else {
                    $q->where('territorio_token_id', $tokenRow->id);
                }
            })
            ->selectRaw('
                eleccion_puesto_id,
                SUM(CASE WHEN estado <> "rechazado" THEN 1 ELSE 0 END) as total_referidos,
                SUM(CASE WHEN estado = "referido" THEN 1 ELSE 0 END) as c_referido,
                SUM(CASE WHEN estado = "asignado" THEN 1 ELSE 0 END) as c_asignado,
                SUM(CASE WHEN estado = "validado" THEN 1 ELSE 0 END) as c_validado,
                SUM(CASE WHEN estado = "postulado" THEN 1 ELSE 0 END) as c_postulado,
                SUM(CASE WHEN estado = "acreditado" THEN 1 ELSE 0 END) as c_acreditado
            ')
            ->groupBy('eleccion_puesto_id')
            ->get()
            ->keyBy('eleccion_puesto_id');

        $puestosAlcance = $puestosAlcance->map(function ($p) use ($referidosAggPorPuesto, $metaPct) {
                $mesasTotal = (int) $p->mesas_total;
                $metaObjetivo = (int) floor($mesasTotal * ($metaPct / 100));
                $remanentesTotal = 0;
                if ($mesasTotal > 0) {
                    $remanentesTotal = $mesasTotal < 10 ? 1 : (int) ceil($mesasTotal * 0.10);
                }
                $agg = $referidosAggPorPuesto->get($p->id);
                $totalReferidos = (int) ($agg->total_referidos ?? 0);

                return (object) [
                    'id' => $p->id,
                    'municipio' => $p->municipio,
                    'puesto' => $p->puesto,
                    'mesas_total' => $mesasTotal,
                    'meta_objetivo' => $metaObjetivo,
                    'remanentes_total' => $remanentesTotal,
                    'total_referidos' => $totalReferidos,
                    'c_referido' => (int) ($agg->c_referido ?? 0),
                    'c_asignado' => (int) ($agg->c_asignado ?? 0),
                    'c_validado' => (int) ($agg->c_validado ?? 0),
                    'c_postulado' => (int) ($agg->c_postulado ?? 0),
                    'c_acreditado' => (int) ($agg->c_acreditado ?? 0),
                    'faltantes' => max($metaObjetivo - $totalReferidos, 0),
                ];
            });

        return view('public.referidos.seguimiento', [
            'token' => $tokenRow,
            'meta_pct' => $metaPct,
            'municipio' => $tokenRow->es_consulta ? 'Múltiples municipios' : ($municipio?->municipio),
            'referidos' => $referidos,
            'puestos_alcance' => $puestosAlcance,
        ]);
    }

    public function edit(string $token, int $referidoId)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        if ($tokenRow->es_consulta) {
            abort(403);
        }

        $referido = DB::table('referidos as r')
            ->join('personas as p', 'p.id', '=', 'r.persona_id')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'r.eleccion_puesto_id')
            ->where('r.id', $referidoId)
            ->where('r.territorio_token_id', $tokenRow->id)
            ->select([
                'r.id',
                'r.persona_id',
                'r.eleccion_id',
                'r.eleccion_puesto_id',
                'r.mesa_num',
                'r.estado',
                'r.cedula_pdf_path',
                'p.cedula',
                'p.nombre',
                'p.email',
                'p.telefono',
                'ep.puesto',
                'ep.municipio',
                'ep.mesas_total',
            ])
            ->first();

        if (!$referido) {
            abort(404);
        }

        $puestos = EleccionPuesto::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->when(!empty($tokenRow->comuna), function ($q) use ($tokenRow) {
                $q->where('comuna', $tokenRow->comuna);
            })
            ->orderBy('puesto')
            ->get(['id', 'puesto', 'municipio', 'mesas_total']);

        // En edición: mostrar el puesto actual y los que aún tengan mesas libres globalmente.
        $puestos = $puestos->filter(function ($p) use ($tokenRow, $referido) {
            if ((int) $p->id === (int) $referido->eleccion_puesto_id) {
                return true;
            }
            return $this->getMesasDisponibles((int) $tokenRow->eleccion_id, (int) $p->id)->count() > 0;
        })->values();

        $mesasDisponibles = $this->getMesasDisponibles(
            (int) $referido->eleccion_id,
            (int) $referido->eleccion_puesto_id,
            (int) $referido->id
        );

        return view('public.referidos.edit', [
            'token' => $tokenRow,
            'referido' => $referido,
            'puestos' => $puestos,
            'mesas_disponibles' => $mesasDisponibles,
        ]);
    }

    public function update(Request $request, string $token, int $referidoId)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        if ($tokenRow->es_consulta) {
            return redirect()->route('public.referidos.seguimiento', $tokenRow->token)
                ->with('error', 'Este token es solo de consulta.');
        }

        $referido = Referido::query()
            ->where('id', $referidoId)
            ->where('territorio_token_id', $tokenRow->id)
            ->firstOrFail();

        $data = $request->validate([
            'cedula' => [
                'required',
                'string',
                'max:50',
                Rule::unique('personas', 'cedula')->ignore($referido->persona_id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('personas', 'email')->ignore($referido->persona_id),
            ],
            'telefono' => ['required', 'string', 'max:50'],
            'puesto_id' => ['required', 'integer', 'exists:eleccion_puestos,id'],
            'mesa_num' => ['required', 'integer', 'min:1'],
            'cedula_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
        ]);

        $puesto = EleccionPuesto::where('id', $data['puesto_id'])
            ->where('eleccion_id', $referido->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->when(!empty($tokenRow->comuna), function ($q) use ($tokenRow) {
                $q->where('comuna', $tokenRow->comuna);
            })
            ->first();
        if (!$puesto) {
            return back()->withErrors(['puesto_id' => 'Puesto no permitido para este territorio.'])->withInput();
        }

        if ((int) $data['mesa_num'] > (int) $puesto->mesas_total) {
            return back()->withErrors(['mesa_num' => 'La mesa excede el total permitido para este puesto.'])->withInput();
        }

        $mesaBloqueada = MesaBloqueo::query()
            ->where('eleccion_id', $referido->eleccion_id)
            ->where('eleccion_puesto_id', $puesto->id)
            ->where('mesa_num', (int) $data['mesa_num'])
            ->exists();
        if ($mesaBloqueada) {
            return back()->withErrors(['mesa_num' => 'La mesa seleccionada está bloqueada por asignación externa.'])->withInput();
        }

        $cupo = $this->getCupoMetrics((int) $referido->eleccion_id, (int) $puesto->id, (int) $referido->id);
        if ((int) $cupo['espacios_40_libres'] <= 0) {
            return back()->withErrors(['puesto_id' => 'Este puesto ya alcanzo su meta de cobertura para esta eleccion.'])->withInput();
        }

        $mesaOcupada = Referido::query()
            ->where('eleccion_id', $referido->eleccion_id)
            ->where('eleccion_puesto_id', $puesto->id)
            ->where('mesa_num', (int) $data['mesa_num'])
            ->where('id', '<>', $referido->id)
            ->where('estado', '<>', 'rechazado')
            ->exists();
        if ($mesaOcupada) {
            return back()->withErrors(['mesa_num' => 'La mesa seleccionada ya no esta disponible.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $nuevoPdfPath = null;
            if ($request->hasFile('cedula_pdf')) {
                $nuevoPdfPath = $request->file('cedula_pdf')->store('cedulas', 'public');
            }

            DB::table('personas')
                ->where('id', $referido->persona_id)
                ->update([
                    'cedula' => $data['cedula'],
                    'nombre' => $data['nombre'],
                    'nombre_original' => $data['nombre'],
                    'nombre_normalizado' => $this->normalizeName($data['nombre']),
                    'email' => $data['email'],
                    'telefono' => $data['telefono'],
                    'updated_at' => now(),
                ]);

            $pdfAnterior = $referido->cedula_pdf_path;
            $referido->eleccion_puesto_id = (int) $data['puesto_id'];
            $referido->mesa_num = (int) $data['mesa_num'];
            if ($nuevoPdfPath) {
                $referido->cedula_pdf_path = $nuevoPdfPath;
            }
            $referido->save();

            if ($nuevoPdfPath && !empty($pdfAnterior) && $pdfAnterior !== $nuevoPdfPath) {
                Storage::disk('public')->delete($pdfAnterior);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Error al actualizar: ' . $e->getMessage()])->withInput();
        }

        return redirect()->route('public.referidos.seguimiento', $tokenRow->token)
            ->with('success', 'Referido actualizado correctamente.');
    }

    public function mesasDisponibles(Request $request, string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        if ($tokenRow->es_consulta) {
            return response()->json(['results' => []]);
        }

        $data = $request->validate([
            'puesto_id' => ['required', 'integer', 'exists:eleccion_puestos,id'],
            'referido_id' => ['nullable', 'integer'],
        ]);

        $puesto = EleccionPuesto::query()
            ->where('id', $data['puesto_id'])
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->when(!empty($tokenRow->comuna), function ($q) use ($tokenRow) {
                $q->where('comuna', $tokenRow->comuna);
            })
            ->first();
        if (!$puesto) {
            return response()->json(['results' => []]);
        }

        $mesas = $this->getMesasDisponibles(
            (int) $tokenRow->eleccion_id,
            (int) $puesto->id,
            (int) ($data['referido_id'] ?? 0)
        );
        $cupo = $this->getCupoMetrics(
            (int) $tokenRow->eleccion_id,
            (int) $puesto->id,
            (int) ($data['referido_id'] ?? 0)
        );

        return response()->json([
            'results' => $mesas->map(fn ($m) => ['id' => $m, 'text' => 'Mesa ' . $m]),
            'max' => (int) $puesto->mesas_total,
            'mesas_no_asignadas' => (int) $mesas->count(),
            'meta_pct' => (int) $cupo['meta_pct'],
            'meta_objetivo' => (int) $cupo['meta_objetivo'],
            'ocupados_meta' => (int) $cupo['ocupados_meta'],
            'espacios_40_libres' => (int) $cupo['espacios_40_libres'],
        ]);
    }

    public function departamentos(Request $request, string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, true);
        if ($tokenRow->es_consulta) {
            return response()->json(['results' => []]);
        }
        $search = trim((string) $request->get('q', ''));

        $query = EleccionPuesto::where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->select('dd', 'departamento')
            ->distinct();

        if ($search !== '') {
            $query->where('departamento', 'like', '%' . $search . '%');
        }

        $items = $query->orderBy('departamento')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->dd,
                'text' => $r->departamento,
            ]);

        return response()->json(['results' => $items]);
    }

    public function municipios(Request $request, string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, true);
        if ($tokenRow->es_consulta) {
            return response()->json(['results' => []]);
        }
        $search = trim((string) $request->get('q', ''));

        $query = EleccionPuesto::where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->select('mm', 'municipio')
            ->distinct();

        if ($search !== '') {
            $query->where('municipio', 'like', '%' . $search . '%');
        }

        $items = $query->orderBy('municipio')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->mm,
                'text' => $r->municipio,
            ]);

        return response()->json(['results' => $items]);
    }

    public function puestos(Request $request, string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, true);
        if ($tokenRow->es_consulta) {
            return response()->json(['results' => []]);
        }

        $query = EleccionPuesto::where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm);

        if ($tokenRow->comuna) {
            $query->where('comuna', $tokenRow->comuna);
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where('puesto', 'like', '%' . $search . '%');
        }

        $items = $query
            ->orderBy('puesto')
            ->limit(50)
            ->get(['id', 'puesto', 'municipio', 'mesas_total'])
            ->map(function ($r) use ($tokenRow) {
                $mesasDisponibles = $this->getMesasDisponibles((int) $tokenRow->eleccion_id, (int) $r->id);
                $cupo = $this->getCupoMetrics((int) $tokenRow->eleccion_id, (int) $r->id);

                return [
                    'id' => $r->id,
                    'text' => $r->puesto . ' - ' . $r->municipio,
                    'mesas_total' => (int) $r->mesas_total,
                    'mesas_disponibles' => $mesasDisponibles->count(),
                    'mesas_no_asignadas' => $mesasDisponibles->count(),
                    'meta_pct' => (int) $cupo['meta_pct'],
                    'meta_objetivo' => (int) $cupo['meta_objetivo'],
                    'ocupados_meta' => (int) $cupo['ocupados_meta'],
                    'espacios_40_libres' => (int) $cupo['espacios_40_libres'],
                ];
            })
            ->filter(function ($r) {
                return (int) ($r['mesas_disponibles'] ?? 0) > 0
                    && (int) ($r['espacios_40_libres'] ?? 0) > 0;
            })
            ->values();

        return response()->json(['results' => $items]);
    }

    private function getTokenOrFail(string $token, bool $requireActive = true): TerritorioToken
    {
        $tokenRow = TerritorioToken::where('token', $token)->first();
        if (!$tokenRow) {
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

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($trans !== false) {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9\s]/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function getMesasDisponibles(int $eleccionId, int $puestoId, int $excludeReferidoId = 0)
    {
        $puesto = EleccionPuesto::query()
            ->where('id', $puestoId)
            ->where('eleccion_id', $eleccionId)
            ->first();
        if (!$puesto || (int) $puesto->mesas_total <= 0) {
            return collect();
        }

        $ocupadas = Referido::query()
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->when($excludeReferidoId > 0, function ($q) use ($excludeReferidoId) {
                $q->where('id', '<>', $excludeReferidoId);
            })
            ->where('estado', '<>', 'rechazado')
            ->pluck('mesa_num')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();

        $bloqueadas = MesaBloqueo::query()
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->pluck('mesa_num')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();

        return collect(range(1, (int) $puesto->mesas_total))
            ->reject(fn ($mesa) => in_array((int) $mesa, $ocupadas, true) || in_array((int) $mesa, $bloqueadas, true))
            ->values();
    }

    private function getMetaPctByEleccion(int $eleccionId): int
    {
        $pct = Eleccion::where('id', $eleccionId)->value('meta_testigos_pct');
        if ($pct === null) {
            return 100;
        }
        return max(0, min(100, (int) $pct));
    }

    private function getCupoMetrics(int $eleccionId, int $puestoId, int $excludeReferidoId = 0): array
    {
        $mesasTotal = (int) DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->count();

        $metaPct = $this->getMetaPctByEleccion($eleccionId);
        $metaObjetivo = (int) floor($mesasTotal * ($metaPct / 100));

        $ocupados = Referido::query()
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->when($excludeReferidoId > 0, function ($q) use ($excludeReferidoId) {
                $q->where('id', '<>', $excludeReferidoId);
            })
            ->where('estado', '<>', 'rechazado')
            ->count();

        return [
            'mesas_total' => $mesasTotal,
            'meta_pct' => $metaPct,
            'meta_objetivo' => $metaObjetivo,
            'ocupados_meta' => (int) $ocupados,
            'espacios_40_libres' => max($metaObjetivo - (int) $ocupados, 0),
        ];
    }
}
