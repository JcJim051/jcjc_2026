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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PublicReferidosController extends Controller
{
    public function form(string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        if ($tokenRow->es_consulta) {
            return redirect()->route('public.referidos.seguimiento', $tokenRow->token);
        }
        if (!$tokenRow->activo || !$this->tokenElectionIsActive($tokenRow) || ($tokenRow->expires_at && $tokenRow->expires_at->isPast())) {
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
        if (!$tokenRow->activo || !$this->tokenElectionIsActive($tokenRow) || ($tokenRow->expires_at && $tokenRow->expires_at->isPast())) {
            return redirect()->route('public.referidos.seguimiento', $tokenRow->token)
                ->with('error', 'Formulario cerrado. Solo seguimiento disponible.');
        }

        $request->merge([
            'cedula' => $this->normalizeCedula((string) $request->input('cedula')),
            'email' => mb_strtolower(trim((string) $request->input('email')), 'UTF-8'),
            'nombre' => trim((string) $request->input('nombre')),
            'telefono' => trim((string) $request->input('telefono')),
        ]);

        $data = $request->validate([
            'cedula' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['required', 'string', 'max:50'],
            'puesto_id' => ['required', 'integer', 'exists:eleccion_puestos,id'],
            'mesa_num' => ['required', 'integer', 'min:1'],
            'cedula_pdf' => ['required', 'file', 'mimes:pdf', 'max:2048'],
        ]);

        $personas = $this->findPersonasByNormalizedCedula($data['cedula']);
        if ($personas->count() > 1) {
            return back()->withErrors([
                'cedula' => 'Encontramos más de una persona con esta cédula. Solicita apoyo administrativo.',
            ])->withInput();
        }

        $personaExistente = $personas->first();
        if ($personaExistente && Referido::query()
            ->where('persona_id', $personaExistente->id)
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->exists()) {
            return back()->withErrors([
                'cedula' => 'Esta persona ya está registrada en esta elección. Puedes editar o reactivar el registro existente.',
            ])->withInput();
        }

        if ($this->emailBelongsToAnotherPerson($data['email'], $personaExistente?->id)) {
            return back()->withErrors([
                'email' => 'El correo pertenece a otra persona registrada. Verifica la información.',
            ])->withInput();
        }

        $puesto = EleccionPuesto::where('id', $data['puesto_id'])
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->first();

        if (!$puesto) {
            return back()->withErrors(['puesto_id' => 'Puesto no permitido para este territorio.']);
        }

        $comunasToken = $this->parseComunasToken($tokenRow->comuna);
        if (!empty($comunasToken) && !in_array((string) $puesto->comuna, $comunasToken, true)) {
            return back()->withErrors(['puesto_id' => 'Puesto no permitido para la comuna del territorio.']);
        }

        if (!$this->mesaExisteEnEleccionPuesto((int) $tokenRow->eleccion_id, (int) $puesto->id, (int) $data['mesa_num'])) {
            return back()->withErrors(['mesa_num' => 'La mesa no existe en DIVIPOL para este puesto.']);
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

        try {
            $result = DB::transaction(function () use ($data, $normalized, $pdfPath, $puesto, $tokenRow) {
                $personas = $this->findPersonasByNormalizedCedula($data['cedula'], true);
                if ($personas->count() > 1) {
                    return [
                        'field' => 'cedula',
                        'message' => 'Encontramos más de una persona con esta cédula. Solicita apoyo administrativo.',
                    ];
                }

                $persona = $personas->first();
                if ($persona && Referido::query()
                    ->where('persona_id', $persona->id)
                    ->where('eleccion_id', $tokenRow->eleccion_id)
                    ->lockForUpdate()
                    ->exists()) {
                    return [
                        'field' => 'cedula',
                        'message' => 'Esta persona ya está registrada en esta elección. Puedes editar o reactivar el registro existente.',
                    ];
                }

                if ($this->emailBelongsToAnotherPerson($data['email'], $persona?->id, true)) {
                    return [
                        'field' => 'email',
                        'message' => 'El correo pertenece a otra persona registrada. Verifica la información.',
                    ];
                }

                if ($persona) {
                    $persona->update([
                        'nombre' => $data['nombre'],
                        'nombre_original' => $data['nombre'],
                        'nombre_normalizado' => $normalized,
                        'email' => $data['email'],
                        'telefono' => $data['telefono'],
                    ]);
                } else {
                    $persona = Persona::create([
                        'cedula' => $data['cedula'],
                        'nombre' => $data['nombre'],
                        'nombre_original' => $data['nombre'],
                        'nombre_normalizado' => $normalized,
                        'email' => $data['email'],
                        'telefono' => $data['telefono'],
                    ]);
                }

                Referido::create([
                    'persona_id' => $persona->id,
                    'eleccion_id' => $tokenRow->eleccion_id,
                    'eleccion_puesto_id' => $puesto->id,
                    'territorio_token_id' => $tokenRow->id,
                    'mesa_num' => (int) $data['mesa_num'],
                    'cedula_pdf_path' => $pdfPath,
                    'estado' => 'referido',
                    'observaciones' => null,
                ]);

                return ['ok' => true];
            });

            if (empty($result['ok'])) {
                Storage::disk('public')->delete($pdfPath);

                return back()->withErrors([
                    $result['field'] => $result['message'],
                ])->withInput();
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($pdfPath);

            try {
                $persona = $this->findPersonasByNormalizedCedula($data['cedula'])->first();
                if ($persona && Referido::query()
                    ->where('persona_id', $persona->id)
                    ->where('eleccion_id', $tokenRow->eleccion_id)
                    ->exists()) {
                    return back()->withErrors([
                        'cedula' => 'Esta persona ya está registrada en esta elección. Puedes editar o reactivar el registro existente.',
                    ])->withInput();
                }
                if ($this->emailBelongsToAnotherPerson($data['email'], $persona?->id)) {
                    return back()->withErrors([
                        'email' => 'El correo pertenece a otra persona registrada. Verifica la información.',
                    ])->withInput();
                }
            } catch (\Throwable $diagnosticError) {
                // Conservamos el error original y evitamos exponer detalles SQL al usuario.
            }

            report($e);

            return back()->withErrors([
                'general' => 'No pudimos guardar el referido. Verifica la información e intenta nuevamente.',
            ])->withInput();
        }

        return redirect()->route('public.referidos.form', $token)
            ->with('success', 'Referido registrado correctamente.');
    }

    public function seguimiento(string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        $metaPct = $this->getMetaPctByEleccion((int) $tokenRow->eleccion_id);
        $municipiosConsulta = collect($tokenRow->municipios ?? [])->filter()->values();
        $comunasToken = $this->parseComunasToken($tokenRow->comuna);

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
                $q->whereIn('comuna', $this->parseComunasToken($tokenRow->comuna));
            })
            ->orderBy('dd')
            ->orderBy('mm')
            ->orderBy('zz')
            ->orderBy('pp')
            ->get([
                'id',
                'municipio',
                'puesto',
                'mesas_total',
                'dd',
                'mm',
                'zz',
                'pp',
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

        $metasPactadasPorPuesto = DB::table('eleccion_puesto_metas')
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->whereIn('eleccion_puesto_id', $puestosAlcance->pluck('id')->all())
            ->pluck('meta_pactada', 'eleccion_puesto_id');

        $puestosAlcance = $puestosAlcance->map(function ($p) use ($referidosAggPorPuesto, $metaPct, $metasPactadasPorPuesto) {
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
                    'meta_pactada' => (int) ($metasPactadasPorPuesto[$p->id] ?? 0),
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

    public function exportResumen(string $token)
    {
        $tokenRow = $this->getTokenOrFail($token, false);
        $metaPct = $this->getMetaPctByEleccion((int) $tokenRow->eleccion_id);
        $municipiosConsulta = collect($tokenRow->municipios ?? [])->filter()->values();

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
                $q->whereIn('comuna', $this->parseComunasToken($tokenRow->comuna));
            })
            ->orderBy('dd')
            ->orderBy('mm')
            ->orderBy('zz')
            ->orderBy('pp')
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
                SUM(CASE WHEN estado = "asignado" THEN 1 ELSE 0 END) as c_asignado,
                SUM(CASE WHEN estado = "validado" THEN 1 ELSE 0 END) as c_validado
            ')
            ->groupBy('eleccion_puesto_id')
            ->get()
            ->keyBy('eleccion_puesto_id');

        $metasPactadasPorPuesto = DB::table('eleccion_puesto_metas')
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->whereIn('eleccion_puesto_id', $puestosAlcance->pluck('id')->all())
            ->pluck('meta_pactada', 'eleccion_puesto_id');

        $fileName = 'resumen_puestos_' . $tokenRow->token . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($puestosAlcance, $referidosAggPorPuesto, $metasPactadasPorPuesto, $metaPct) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Resumen');
            $sheet->fromArray([
                'Municipio', 'Puesto', 'Mesas Total', 'Meta', 'Meta pactada', 'Registrados', 'Asignado', 'Validado', 'Faltan',
            ], null, 'A1');

            $rowIndex = 2;
            foreach ($puestosAlcance as $p) {
                $agg = $referidosAggPorPuesto->get($p->id);
                $mesasTotal = (int) ($p->mesas_total ?? 0);
                $meta = (int) floor($mesasTotal * ($metaPct / 100));
                $registrados = (int) ($agg->total_referidos ?? 0);
                $sheet->fromArray([
                    $p->municipio,
                    $p->puesto,
                    $mesasTotal,
                    $meta,
                    (int) ($metasPactadasPorPuesto[$p->id] ?? 0),
                    $registrados,
                    (int) ($agg->c_asignado ?? 0),
                    (int) ($agg->c_validado ?? 0),
                    max($meta - $registrados, 0),
                ], null, 'A' . $rowIndex);
                $rowIndex++;
            }

            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
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
            ->when(!empty($comunasToken = $this->parseComunasToken($tokenRow->comuna)), function ($q) use ($comunasToken) {
                $q->whereIn('comuna', $comunasToken);
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

        $request->merge([
            'cedula' => $this->normalizeCedula((string) $request->input('cedula')),
            'email' => mb_strtolower(trim((string) $request->input('email')), 'UTF-8'),
            'nombre' => trim((string) $request->input('nombre')),
            'telefono' => trim((string) $request->input('telefono')),
        ]);

        $data = $request->validate([
            'cedula' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['required', 'string', 'max:50'],
            'puesto_id' => ['required', 'integer', 'exists:eleccion_puestos,id'],
            'mesa_num' => ['required', 'integer', 'min:1'],
            'cedula_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:2048'],
        ]);

        $personasCedula = $this->findPersonasByNormalizedCedula($data['cedula']);
        if ($personasCedula->contains(fn ($persona) => (int) $persona->id !== (int) $referido->persona_id)) {
            return back()->withErrors([
                'cedula' => 'La cédula pertenece a otra persona registrada. Verifica la información.',
            ])->withInput();
        }
        if ($this->emailBelongsToAnotherPerson($data['email'], (int) $referido->persona_id)) {
            return back()->withErrors([
                'email' => 'El correo pertenece a otra persona registrada. Verifica la información.',
            ])->withInput();
        }

        $puesto = EleccionPuesto::where('id', $data['puesto_id'])
            ->where('eleccion_id', $referido->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->when(!empty($comunasToken = $this->parseComunasToken($tokenRow->comuna)), function ($q) use ($comunasToken) {
                $q->whereIn('comuna', $comunasToken);
            })
            ->first();
        if (!$puesto) {
            return back()->withErrors(['puesto_id' => 'Puesto no permitido para este territorio.'])->withInput();
        }

        if (!$this->mesaExisteEnEleccionPuesto((int) $referido->eleccion_id, (int) $puesto->id, (int) $data['mesa_num'])) {
            return back()->withErrors(['mesa_num' => 'La mesa no existe en DIVIPOL para este puesto.'])->withInput();
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

        $nuevoPdfPath = null;
        try {
            if ($request->hasFile('cedula_pdf')) {
                $nuevoPdfPath = $request->file('cedula_pdf')->store('cedulas', 'public');
            }

            $pdfAnterior = $referido->cedula_pdf_path;

            DB::transaction(function () use ($data, $referido, $nuevoPdfPath) {
                $persona = Persona::query()->whereKey($referido->persona_id)->lockForUpdate()->firstOrFail();
                $personasCedula = $this->findPersonasByNormalizedCedula($data['cedula'], true);
                if ($personasCedula->contains(fn ($item) => (int) $item->id !== (int) $persona->id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'cedula' => 'La cédula pertenece a otra persona registrada. Verifica la información.',
                    ]);
                }
                if ($this->emailBelongsToAnotherPerson($data['email'], (int) $persona->id, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'email' => 'El correo pertenece a otra persona registrada. Verifica la información.',
                    ]);
                }

                $persona->update([
                    'cedula' => $data['cedula'],
                    'nombre' => $data['nombre'],
                    'nombre_original' => $data['nombre'],
                    'nombre_normalizado' => $this->normalizeName($data['nombre']),
                    'email' => $data['email'],
                    'telefono' => $data['telefono'],
                ]);

                $referido->eleccion_puesto_id = (int) $data['puesto_id'];
                $referido->mesa_num = (int) $data['mesa_num'];
                if ($nuevoPdfPath) {
                    $referido->cedula_pdf_path = $nuevoPdfPath;
                }
                $referido->save();
            });

            if ($nuevoPdfPath && !empty($pdfAnterior) && $pdfAnterior !== $nuevoPdfPath) {
                Storage::disk('public')->delete($pdfAnterior);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($nuevoPdfPath) {
                Storage::disk('public')->delete($nuevoPdfPath);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($nuevoPdfPath) {
                Storage::disk('public')->delete($nuevoPdfPath);
            }
            report($e);

            return back()->withErrors([
                'general' => 'No pudimos actualizar el referido. Verifica la información e intenta nuevamente.',
            ])->withInput();
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
        if (!$this->tokenElectionIsActive($tokenRow)) {
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
            ->when(!empty($comunasToken = $this->parseComunasToken($tokenRow->comuna)), function ($q) use ($comunasToken) {
                $q->whereIn('comuna', $comunasToken);
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
            'max' => (int) DB::table('eleccion_mesas')
                ->where('eleccion_id', $tokenRow->eleccion_id)
                ->where('eleccion_puesto_id', $puesto->id)
                ->max('mesa_num'),
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
        if (!$this->tokenElectionIsActive($tokenRow)) {
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
        if (!$this->tokenElectionIsActive($tokenRow)) {
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
        if (!$this->tokenElectionIsActive($tokenRow)) {
            return response()->json(['results' => []]);
        }

        $query = EleccionPuesto::where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm);

        $comunasToken = $this->parseComunasToken($tokenRow->comuna);
        if (!empty($comunasToken)) {
            $query->whereIn('comuna', $comunasToken);
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

    private function tokenElectionIsActive(TerritorioToken $tokenRow): bool
    {
        return Eleccion::where('id', $tokenRow->eleccion_id)
            ->where('estado', 'activa')
            ->exists();
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

    private function normalizeCedula(?string $value): string
    {
        return preg_replace('/[\s.,-]+/', '', trim((string) $value)) ?? '';
    }

    private function findPersonasByNormalizedCedula(string $cedula, bool $lock = false)
    {
        $normalized = $this->normalizeCedula($cedula);
        $query = Persona::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(cedula, '.', ''), ',', ''), '-', ''), ' ', '') = ?",
                [$normalized]
            )
            ->orderBy('id')
            ->limit(2);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function emailBelongsToAnotherPerson(string $email, ?int $personaId = null, bool $lock = false): bool
    {
        $query = Persona::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($email), 'UTF-8')])
            ->when($personaId, fn ($q) => $q->where('id', '<>', $personaId));

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function getMesasDisponibles(int $eleccionId, int $puestoId, int $excludeReferidoId = 0)
    {
        $mesasUniverso = DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->pluck('mesa_num')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->sort()
            ->values();

        if ($mesasUniverso->isEmpty()) {
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

        return $mesasUniverso
            ->reject(fn ($mesa) => in_array((int) $mesa, $ocupadas, true) || in_array((int) $mesa, $bloqueadas, true))
            ->values();
    }

    private function mesaExisteEnEleccionPuesto(int $eleccionId, int $puestoId, int $mesaNum): bool
    {
        return DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->where('mesa_num', $mesaNum)
            ->exists();
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
        $metaObjetivoOficial = (int) floor($mesasTotal * ($metaPct / 100));
        $metaPactada = (int) DB::table('eleccion_puesto_metas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->value('meta_pactada');
        $metaObjetivo = $metaPactada > 0 ? $metaPactada : $metaObjetivoOficial;

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
            'meta_objetivo_oficial' => $metaObjetivoOficial,
            'meta_pactada' => $metaPactada,
            'meta_objetivo' => $metaObjetivo,
            'ocupados_meta' => (int) $ocupados,
            'espacios_40_libres' => max($metaObjetivo - (int) $ocupados, 0),
        ];
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
}
