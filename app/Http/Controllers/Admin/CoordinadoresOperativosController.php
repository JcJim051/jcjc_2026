<?php

namespace App\Http\Controllers\Admin;

use App\Models\Abogado;
use App\Models\AbogadoCoordinacion;
use App\Models\Eleccion;
use App\Traits\EleccionScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CoordinadoresOperativosController extends AbogadosController
{
    use EleccionScope;

    public function index()
    {
        $request = request();
        $elecciones = Eleccion::query()
            ->orderByRaw("CASE WHEN estado = 'activa' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get(['id', 'nombre', 'tipo', 'estado']);

        $eleccionId = (int) ($request->get('eleccion_id') ?: $this->resolveEleccionId() ?: optional($elecciones->first())->id);
        $dataset = $this->buildDataset($eleccionId);

        return view('admin.coordinadores_operativos.index', array_merge($dataset, [
            'elecciones' => $elecciones,
            'eleccionId' => $eleccionId,
        ]));
    }

    public function export(Request $request)
    {
        $eleccionId = (int) ($request->get('eleccion_id') ?: $this->resolveEleccionId());
        $dataset = $this->buildDataset($eleccionId);
        $eleccion = $dataset['eleccion'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Coordinadores');

        $headers = [
            'Municipio', 'Comuna', 'Puesto', 'Mesas', 'Rem permitidos', 'Rem ocupados',
            'En mesa', 'Sin acreditar', 'Validados', 'Disponibles rem', 'Detalle coordinadores'
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($dataset['rows'] as $row) {
            $sheet->fromArray([
                $row['municipio'],
                $row['comuna'],
                $row['puesto'],
                $row['mesas_total'],
                $row['rem_permitidos'],
                $row['coordinadores_rem'],
                $row['coordinadores_mesa'],
                $row['coordinadores_sin_acreditar'],
                $row['coordinadores_validados'],
                $row['rem_disponibles'],
                implode(' | ', $row['coordinadores']),
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $file = tempnam(sys_get_temp_dir(), 'coord_divipol_');
        (new Xlsx($spreadsheet))->save($file);
        $spreadsheet->disconnectWorksheets();

        $name = 'coordinadores_divipol_' . ($eleccion ? $this->slugify($eleccion->nombre) : 'sin_eleccion') . '.xlsx';

        return response()->download($file, $name)->deleteFileAfterSend(true);
    }

    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'codpuesto' => 'required|string',
            'tipo_asignacion' => 'required|string|in:remanente,mesa,sin_acreditar',
            'mesa_num' => 'nullable|string|max:20',
            'nombre' => 'required|string|max:191',
            'cc' => 'required|string|max:50',
            'correo' => 'nullable|email|max:191',
            'telefono' => 'nullable|string|max:50',
            'observacion' => 'nullable|string|max:500',
        ]);

        $eleccion = Eleccion::where('id', $data['eleccion_id'])->where('estado', 'activa')->first();
        if (!$eleccion) {
            return back()->withErrors(['eleccion_id' => 'Solo puedes crear coordinadores manualmente en una elección activa.'])->withInput();
        }

        $puesto = $this->findEleccionPuestoByCodigoLocal((int) $data['eleccion_id'], trim((string) $data['codpuesto']));
        if (!$puesto) {
            return back()->withErrors(['codpuesto' => 'El puesto seleccionado no existe en la DIVIPOL de la elección.'])->withInput();
        }

        $cc = $this->digitsOnlyLocal($data['cc']);
        if ($cc === '') {
            return back()->withErrors(['cc' => 'La cédula es obligatoria.'])->withInput();
        }

        $assignment = null;

        try {
            DB::transaction(function () use ($data, $eleccion, $puesto, $cc, &$assignment) {
                $codpuesto = $this->buildEleccionPuestoKeyLocal((object) $puesto);

                $abogado = Abogado::where('cc', $cc)->lockForUpdate()->first();
                if ($abogado) {
                    $abogado->fill([
                        'nombre' => trim((string) $data['nombre']),
                        'correo' => trim((string) ($data['correo'] ?? '')) ?: ($abogado->correo ?: ($cc . '@sin-correo.local')),
                        'telefono' => trim((string) ($data['telefono'] ?? '')) ?: ($abogado->telefono ?: 'N/D'),
                        'comuna' => trim((string) ($puesto->comuna ?? 'N/D')) ?: 'N/D',
                        'puesto' => trim(((string) ($puesto->municipio ?? '')) . ' - ' . ((string) ($puesto->puesto ?? 'N/D')), ' -'),
                        'observacion' => trim((string) ($data['observacion'] ?? '')) ?: $abogado->observacion,
                        'activo' => true,
                    ])->save();
                } else {
                    $abogado = Abogado::create([
                        'nombre' => trim((string) $data['nombre']),
                        'cc' => $cc,
                        'correo' => trim((string) ($data['correo'] ?? '')) ?: ($cc . '@sin-correo.local'),
                        'telefono' => trim((string) ($data['telefono'] ?? '')) ?: 'N/D',
                        'comuna' => trim((string) ($puesto->comuna ?? 'N/D')) ?: 'N/D',
                        'puesto' => trim(((string) ($puesto->municipio ?? '')) . ' - ' . ((string) ($puesto->puesto ?? 'N/D')), ' -'),
                        'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
                        'activo' => true,
                    ]);
                }

                $yaAsignadoMismoPuesto = AbogadoCoordinacion::where('abogado_id', $abogado->id)
                    ->where('eleccion_id', $eleccion->id)
                    ->where('codpuesto', $codpuesto)
                    ->whereNull('released_at')
                    ->lockForUpdate()
                    ->exists();

                if ($yaAsignadoMismoPuesto) {
                    throw new \RuntimeException('Esta persona ya tiene una coordinación activa en este mismo puesto para esta elección.');
                }

                $assignment = $this->resolveManualCoordinatorAssignment(
                    (int) $eleccion->id,
                    (object) $puesto,
                    $codpuesto,
                    (string) $data['tipo_asignacion'],
                    trim((string) ($data['mesa_num'] ?? ''))
                );

                $abogado->mesa = $assignment['abogado_mesa'];
                $abogado->save();

                AbogadoCoordinacion::create([
                    'abogado_id' => $abogado->id,
                    'eleccion_id' => $eleccion->id,
                    'eleccion_mesa_id' => $assignment['eleccion_mesa_id'],
                    'codpuesto' => $codpuesto,
                    'seller_id' => null,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => Carbon::now('America/Bogota'),
                    'validacion_estado' => $assignment['validacion_estado'],
                    'observacion' => trim(((string) ($data['observacion'] ?? '')) ?: 'Creado manualmente desde módulo Coordinadores') . ' | ' . $assignment['note'],
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['codpuesto' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.coordinadores_operativos.index', ['eleccion_id' => $eleccion->id])
            ->with('info', 'Coordinador creado correctamente como ' . ($assignment['label'] ?? 'coordinador') . '.');
    }

    public function integrateExisting(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
        ]);

        $eleccion = Eleccion::find($data['eleccion_id']);
        if (!$eleccion) {
            return back()->withErrors(['eleccion_id' => 'La elección seleccionada no existe.']);
        }

        $summary = $this->normalizeExistingAssignments((int) $eleccion->id);

        return redirect()
            ->route('admin.coordinadores_operativos.index', ['eleccion_id' => $eleccion->id])
            ->with('info', "Integración rápida completada. Rem {$summary['remanente']}, Mesa {$summary['mesa']}, Sin acreditar {$summary['sin_acreditar']}, Ajustados {$summary['actualizados']}.");
    }

    public function updateManual(Request $request, AbogadoCoordinacion $coordinacion)
    {
        if ($coordinacion->released_at) {
            return back()->withErrors(['coordinacion' => 'La coordinación seleccionada ya fue liberada.']);
        }

        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'codpuesto' => 'required|string',
            'tipo_asignacion' => 'required|string|in:remanente,mesa,sin_acreditar',
            'mesa_num' => 'nullable|string|max:20',
            'nombre' => 'required|string|max:191',
            'cc' => 'required|string|max:50',
            'correo' => 'nullable|email|max:191',
            'telefono' => 'nullable|string|max:50',
            'observacion' => 'nullable|string|max:500',
        ]);

        if ((int) $coordinacion->eleccion_id !== (int) $data['eleccion_id']) {
            return back()->withErrors(['coordinacion' => 'La coordinación no pertenece a la elección seleccionada.']);
        }

        $eleccion = Eleccion::find($data['eleccion_id']);
        $puesto = $this->findEleccionPuestoByCodigoLocal((int) $data['eleccion_id'], trim((string) $data['codpuesto']));
        if (!$eleccion || !$puesto) {
            return back()->withErrors(['codpuesto' => 'El puesto seleccionado no existe en la DIVIPOL de la elección.'])->withInput();
        }

        $cc = $this->digitsOnlyLocal($data['cc']);
        if ($cc === '') {
            return back()->withErrors(['cc' => 'La cédula es obligatoria.'])->withInput();
        }

        $assignment = null;

        try {
            DB::transaction(function () use ($data, $coordinacion, $puesto, $cc, &$assignment) {
                $coordinacion = AbogadoCoordinacion::with('abogado')
                    ->whereKey($coordinacion->id)
                    ->whereNull('released_at')
                    ->lockForUpdate()
                    ->firstOrFail();

                $abogado = Abogado::whereKey($coordinacion->abogado_id)->lockForUpdate()->firstOrFail();

                if ($cc !== $abogado->cc) {
                    $duplicado = Abogado::where('cc', $cc)->where('id', '<>', $abogado->id)->lockForUpdate()->exists();
                    if ($duplicado) {
                        throw new \RuntimeException('La cédula ya pertenece a otro coordinador caracterizado.');
                    }
                }

                $codpuesto = $this->buildEleccionPuestoKeyLocal((object) $puesto);
                $duplicadaMismoPuesto = AbogadoCoordinacion::where('abogado_id', $abogado->id)
                    ->where('eleccion_id', (int) $data['eleccion_id'])
                    ->where('codpuesto', $codpuesto)
                    ->whereNull('released_at')
                    ->where('id', '<>', (int) $coordinacion->id)
                    ->lockForUpdate()
                    ->exists();

                if ($duplicadaMismoPuesto) {
                    throw new \RuntimeException('Esta persona ya tiene otra coordinación activa en este mismo puesto para esta elección.');
                }

                $assignment = $this->resolveManualCoordinatorAssignment(
                    (int) $data['eleccion_id'],
                    (object) $puesto,
                    $codpuesto,
                    (string) $data['tipo_asignacion'],
                    trim((string) ($data['mesa_num'] ?? '')),
                    (int) $coordinacion->id
                );

                $abogado->fill([
                    'nombre' => trim((string) $data['nombre']),
                    'cc' => $cc,
                    'correo' => trim((string) ($data['correo'] ?? '')) ?: ($abogado->correo ?: ($cc . '@sin-correo.local')),
                    'telefono' => trim((string) ($data['telefono'] ?? '')) ?: ($abogado->telefono ?: 'N/D'),
                    'comuna' => trim((string) ($puesto->comuna ?? 'N/D')) ?: 'N/D',
                    'puesto' => trim(((string) ($puesto->municipio ?? '')) . ' - ' . ((string) ($puesto->puesto ?? 'N/D')), ' -'),
                    'mesa' => $assignment['abogado_mesa'],
                    'observacion' => trim((string) ($data['observacion'] ?? '')) ?: $abogado->observacion,
                    'activo' => true,
                ])->save();

                $coordinacion->codpuesto = $codpuesto;
                $coordinacion->eleccion_mesa_id = $assignment['eleccion_mesa_id'];
                $coordinacion->validacion_estado = $coordinacion->validacion_estado === 'validado'
                    ? 'validado'
                    : $assignment['validacion_estado'];
                $coordinacion->observacion = trim(((string) ($data['observacion'] ?? '')) ?: $coordinacion->observacion);
                $coordinacion->save();
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['coordinacion' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.coordinadores_operativos.index', ['eleccion_id' => $data['eleccion_id']])
            ->with('info', 'Coordinador actualizado correctamente como ' . ($assignment['label'] ?? 'coordinador') . '.');
    }

    private function buildDataset(int $eleccionId): array
    {
        $eleccion = $eleccionId > 0 ? Eleccion::find($eleccionId) : null;
        if (!$eleccion) {
            return [
                'eleccion' => null,
                'rows' => collect(),
                'summary' => ['puestos' => 0, 'coordinadores' => 0, 'validados' => 0, 'disponibles' => 0, 'rem' => 0, 'mesa' => 0, 'sin_acreditar' => 0],
                'puestoOptions' => collect(),
            ];
        }

        $puestos = DB::table('eleccion_puestos')
            ->where('eleccion_id', $eleccionId)
            ->orderByRaw('CAST(mm AS UNSIGNED) asc')
            ->orderByRaw('CAST(zz AS UNSIGNED) asc')
            ->orderBy('zz')
            ->orderBy('pp')
            ->get();

        $mesasPorPuesto = DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->select('eleccion_puesto_id', DB::raw('COUNT(*) as total'))
            ->groupBy('eleccion_puesto_id')
            ->pluck('total', 'eleccion_puesto_id');

        $coordinaciones = DB::table('abogado_coordinaciones as ac')
            ->join('abogados as a', 'a.id', '=', 'ac.abogado_id')
            ->leftJoin('eleccion_mesas as em', 'em.id', '=', 'ac.eleccion_mesa_id')
            ->where('ac.eleccion_id', $eleccionId)
            ->whereNull('ac.released_at')
            ->orderBy('a.nombre')
            ->get([
                'ac.id as coordinacion_id',
                'ac.abogado_id',
                'ac.codpuesto',
                'ac.validacion_estado',
                'ac.eleccion_mesa_id',
                'ac.observacion',
                'a.nombre',
                'a.cc',
                'a.correo',
                'a.telefono',
                'em.mesa_num',
            ])
            ->groupBy('codpuesto');

        $puestoOptions = $puestos->map(function ($puesto) use ($eleccionId) {
            $codpuesto = $this->buildEleccionPuestoKeyLocal((object) $puesto);
            $remPermitidos = $this->getRemanentesPermitidosLocal($eleccionId, $codpuesto);
            $remOcupados = $this->countRemanentesOcupadosLocal($eleccionId, $codpuesto);
            $mesasLibres = $this->freeMesasLocal($eleccionId, (int) $puesto->id);
            $mesaLibre = $mesasLibres->count();

            return (object) [
                'codpuesto' => $codpuesto,
                'label' => trim(((string) ($puesto->municipio ?? 'N/D')) . ' - ' . ((string) ($puesto->puesto ?? 'N/D'))),
                'disponibles' => max(0, $remPermitidos - $remOcupados),
                'mesa_libre' => $mesaLibre,
                'mesas_disponibles' => $mesasLibres->pluck('mesa_num')->map(fn ($mesa) => (string) $mesa)->values()->all(),
                'tipo_sugerido' => ($remPermitidos > $remOcupados) ? 'Rem' : ($mesaLibre > 0 ? 'Mesa' : 'Sin acreditar'),
            ];
        });

        $rows = $puestos->map(function ($puesto) use ($eleccionId, $mesasPorPuesto, $coordinaciones) {
            $codpuesto = $this->buildEleccionPuestoKeyLocal((object) $puesto);
            $group = collect($coordinaciones->get($codpuesto, []))->values();
            $mesasTotal = (int) ($mesasPorPuesto[$puesto->id] ?? ($puesto->mesas_total ?? 0));
            $remPermitidos = $this->getRemanentesPermitidosLocal($eleccionId, $codpuesto);
            $remCount = $group->filter(fn ($coord) => $this->coordTipoLocal($coord) === 'remanente')->count();
            $mesaCount = $group->filter(fn ($coord) => $this->coordTipoLocal($coord) === 'mesa')->count();
            $sinAcreditarCount = $group->filter(fn ($coord) => $this->coordTipoLocal($coord) === 'sin_acreditar')->count();
            $validados = $group->where('validacion_estado', 'validado')->count();

            return [
                'codpuesto' => $codpuesto,
                'municipio_codigo' => (string) ($puesto->mm ?? ''),
                'municipio' => (string) ($puesto->municipio ?? 'N/D'),
                'comuna' => (string) ($puesto->comuna ?: 'N/D'),
                'puesto' => (string) ($puesto->puesto ?? 'N/D'),
                'mesas_total' => $mesasTotal,
                'rem_permitidos' => $remPermitidos,
                'coordinadores_activos' => $group->count(),
                'coordinadores_validados' => $validados,
                'coordinadores_rem' => $remCount,
                'coordinadores_mesa' => $mesaCount,
                'coordinadores_sin_acreditar' => $sinAcreditarCount,
                'rem_disponibles' => max(0, $remPermitidos - $remCount),
                'coordinadores' => $group->map(function ($coord) use ($codpuesto, $puesto) {
                    $tipo = $this->coordTipoLocal($coord);
                    $label = $tipo === 'mesa'
                        ? 'Mesa ' . $coord->mesa_num
                        : ($tipo === 'sin_acreditar' ? 'Sin acreditar' : 'Rem');

                    return [
                        'coordinacion_id' => (int) $coord->coordinacion_id,
                        'abogado_id' => (int) $coord->abogado_id,
                        'nombre' => (string) ($coord->nombre ?? 'N/D'),
                        'cc' => (string) ($coord->cc ?? 'N/D'),
                        'correo' => (string) ($coord->correo ?? ''),
                        'telefono' => (string) ($coord->telefono ?? ''),
                        'tipo' => $tipo,
                        'tipo_label' => $label,
                        'mesa_num' => $coord->mesa_num !== null ? (string) $coord->mesa_num : '',
                        'codpuesto' => $codpuesto,
                        'puesto_label' => trim(((string) ($puesto->municipio ?? 'N/D')) . ' - ' . ((string) ($puesto->puesto ?? 'N/D'))),
                        'observacion' => (string) ($coord->observacion ?? ''),
                        'liberar_url' => route('admin.abogados.liberar_coordinador', [
                            'abogado' => (int) $coord->abogado_id,
                            'coordinacion' => (int) $coord->coordinacion_id,
                        ]),
                    ];
                })->values()->all(),
            ];
        })->sortBy([
            ['municipio_codigo', 'asc'],
            ['comuna', 'asc'],
            ['puesto', 'asc'],
        ])->values();

        return [
            'eleccion' => $eleccion,
            'rows' => $rows,
            'summary' => [
                'puestos' => $rows->count(),
                'coordinadores' => $rows->sum('coordinadores_activos'),
                'validados' => $rows->sum('coordinadores_validados'),
                'disponibles' => $rows->sum('rem_disponibles'),
                'rem' => $rows->sum('coordinadores_rem'),
                'mesa' => $rows->sum('coordinadores_mesa'),
                'sin_acreditar' => $rows->sum('coordinadores_sin_acreditar'),
            ],
            'puestoOptions' => $puestoOptions,
        ];
    }

    private function coordTipoLocal($coord): string
    {
        if (!empty($coord->eleccion_mesa_id)) {
            return 'mesa';
        }
        if (($coord->validacion_estado ?? null) === 'sin_acreditar') {
            return 'sin_acreditar';
        }
        return 'remanente';
    }

    private function buildEleccionPuestoKeyLocal(object $puesto): string
    {
        $codigo = trim((string) ($puesto->codigo_puesto ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        return (string) ($puesto->dd ?? '') . (string) ($puesto->mm ?? '') . (string) ($puesto->zz ?? '') . (string) ($puesto->pp ?? '');
    }

    private function findEleccionPuestoByCodigoLocal(int $eleccionId, string $codpuesto): ?object
    {
        return DB::table('eleccion_puestos')
            ->where('eleccion_id', $eleccionId)
            ->where(function ($q) use ($codpuesto) {
                $q->where('codigo_puesto', $codpuesto)
                    ->orWhere(DB::raw('CONCAT(dd,mm,zz,pp)'), $codpuesto)
                    ->orWhere('pp', $codpuesto);
            })
            ->first();
    }

    private function getRemanentesPermitidosLocal(int $eleccionId, string $codpuesto): int
    {
        if (!$eleccionId || $codpuesto === '') {
            return 0;
        }

        $puesto = $this->findEleccionPuestoByCodigoLocal($eleccionId, $codpuesto);
        if (!$puesto) {
            return 0;
        }

        $totalMesas = (int) DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puesto->id)
            ->count();

        if ($totalMesas <= 0) {
            $totalMesas = (int) ($puesto->mesas_total ?? 0);
        }

        if ($totalMesas <= 0) {
            return 0;
        }

        return $totalMesas < 10 ? 1 : (int) ceil($totalMesas * 0.10);
    }

    private function countRemanentesOcupadosLocal(int $eleccionId, string $codpuesto, ?int $excludeCoordinacionId = null): int
    {
        $query = AbogadoCoordinacion::where('eleccion_id', $eleccionId)
            ->where('codpuesto', $codpuesto)
            ->whereNull('released_at')
            ->whereNull('eleccion_mesa_id')
            ->where(function ($q) {
                $q->whereNull('validacion_estado')
                    ->orWhere('validacion_estado', '<>', 'sin_acreditar');
            });

        if ($excludeCoordinacionId) {
            $query->where('id', '<>', $excludeCoordinacionId);
        }

        return $query->count();
    }

    private function freeMesasLocal(int $eleccionId, int $puestoId, ?int $excludeCoordinacionId = null)
    {
        $ocupadas = DB::table('referidos')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->where('estado', '<>', 'rechazado')
            ->pluck('mesa_num')
            ->map(fn ($mesa) => (int) $mesa)
            ->unique()
            ->all();

        $ocupadasPorCoordinadores = DB::table('abogado_coordinaciones as ac')
            ->join('eleccion_mesas as em', 'em.id', '=', 'ac.eleccion_mesa_id')
            ->where('ac.eleccion_id', $eleccionId)
            ->whereNull('ac.released_at')
            ->where('em.eleccion_puesto_id', $puestoId)
            ->when($excludeCoordinacionId, fn ($q) => $q->where('ac.id', '<>', $excludeCoordinacionId))
            ->pluck('em.mesa_num')
            ->map(fn ($mesa) => (int) $mesa)
            ->unique()
            ->all();

        $ocupadas = array_values(array_unique(array_merge($ocupadas, $ocupadasPorCoordinadores)));

        return DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->whereNotIn('mesa_num', $ocupadas)
            ->orderByDesc('mesa_num')
            ->get(['id', 'mesa_num'])
            ->values();
    }

    private function countFreeMesasLocal(int $eleccionId, int $puestoId): int
    {
        return $this->freeMesasLocal($eleccionId, $puestoId)->count();
    }

    private function resolveCoordinatorAssignmentLocal(int $eleccionId, object $puesto, string $codpuesto): array
    {
        $totalRem = $this->getRemanentesPermitidosLocal($eleccionId, $codpuesto);
        $ocupadosRem = $this->countRemanentesOcupadosLocal($eleccionId, $codpuesto);

        if ($totalRem > 0 && $ocupadosRem < $totalRem) {
            return [
                'tipo' => 'remanente',
                'label' => 'Rem',
                'eleccion_mesa_id' => null,
                'validacion_estado' => 'asignado',
                'abogado_mesa' => 'Rem',
                'note' => 'Autoasignado como remanente',
            ];
        }

        $freeMesa = $this->freeMesasLocal($eleccionId, (int) $puesto->id)->first();
        if ($freeMesa) {
            return [
                'tipo' => 'mesa',
                'label' => 'Mesa ' . $freeMesa->mesa_num,
                'eleccion_mesa_id' => (int) $freeMesa->id,
                'validacion_estado' => 'asignado',
                'abogado_mesa' => (string) $freeMesa->mesa_num,
                'note' => 'Autoasignado a mesa ' . $freeMesa->mesa_num,
            ];
        }

        return [
            'tipo' => 'sin_acreditar',
            'label' => 'Sin acreditar',
            'eleccion_mesa_id' => null,
            'validacion_estado' => 'sin_acreditar',
            'abogado_mesa' => 'Sin acreditar',
            'note' => 'Sin cupo remanente ni mesa libre',
        ];
    }

    private function resolveManualCoordinatorAssignment(int $eleccionId, object $puesto, string $codpuesto, string $tipo, string $mesaNum, ?int $excludeCoordinacionId = null): array
    {
        if ($tipo === 'remanente') {
            $totalRem = $this->getRemanentesPermitidosLocal($eleccionId, $codpuesto);
            $ocupadosRem = $this->countRemanentesOcupadosLocal($eleccionId, $codpuesto, $excludeCoordinacionId);

            if ($totalRem <= $ocupadosRem) {
                throw new \RuntimeException('Ya no hay espacios rem disponibles en este puesto.');
            }

            return [
                'tipo' => 'remanente',
                'label' => 'Rem',
                'eleccion_mesa_id' => null,
                'validacion_estado' => 'asignado',
                'abogado_mesa' => 'Rem',
                'note' => 'Asignado manualmente como remanente',
            ];
        }

        if ($tipo === 'mesa') {
            if ($mesaNum === '') {
                throw new \RuntimeException('Debes seleccionar una mesa disponible.');
            }

            $mesa = DB::table('eleccion_mesas')
                ->where('eleccion_id', $eleccionId)
                ->where('eleccion_puesto_id', (int) $puesto->id)
                ->where('mesa_num', (int) $mesaNum)
                ->first(['id', 'mesa_num']);

            if (!$mesa) {
                throw new \RuntimeException('La mesa seleccionada no existe en este puesto.');
            }

            $mesaDisponible = $this->freeMesasLocal($eleccionId, (int) $puesto->id, $excludeCoordinacionId)
                ->firstWhere('id', (int) $mesa->id);

            if (!$mesaDisponible) {
                throw new \RuntimeException('La mesa seleccionada ya no está disponible.');
            }

            return [
                'tipo' => 'mesa',
                'label' => 'Mesa ' . $mesa->mesa_num,
                'eleccion_mesa_id' => (int) $mesa->id,
                'validacion_estado' => 'asignado',
                'abogado_mesa' => (string) $mesa->mesa_num,
                'note' => 'Asignado manualmente a mesa ' . $mesa->mesa_num,
            ];
        }

        return [
            'tipo' => 'sin_acreditar',
            'label' => 'Sin acreditar',
            'eleccion_mesa_id' => null,
            'validacion_estado' => 'sin_acreditar',
            'abogado_mesa' => 'Sin acreditar',
            'note' => 'Asignado manualmente sin acreditar',
        ];
    }

    private function normalizeExistingAssignments(int $eleccionId): array
    {
        $summary = [
            'remanente' => 0,
            'mesa' => 0,
            'sin_acreditar' => 0,
            'actualizados' => 0,
        ];

        $now = Carbon::now('America/Bogota');
        $userName = optional(auth()->user())->name ?? 'administrador';

        DB::transaction(function () use ($eleccionId, &$summary, $now, $userName) {
            $coordinaciones = AbogadoCoordinacion::query()
                ->with(['abogado:id,nombre,mesa', 'eleccionMesa:id,mesa_num,eleccion_puesto_id'])
                ->where('eleccion_id', $eleccionId)
                ->whereNull('released_at')
                ->orderBy('codpuesto')
                ->orderBy('assigned_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('codpuesto');

            foreach ($coordinaciones as $codpuesto => $group) {
                $puesto = $this->findEleccionPuestoByCodigoLocal($eleccionId, (string) $codpuesto);
                if (!$puesto) {
                    foreach ($group as $coordinacion) {
                        $this->syncCoordinatorRecord($coordinacion, [
                            'tipo' => 'sin_acreditar',
                            'label' => 'Sin acreditar',
                            'eleccion_mesa_id' => null,
                            'validacion_estado' => 'sin_acreditar',
                            'abogado_mesa' => 'Sin acreditar',
                            'note' => 'Integración rápida: puesto no encontrado en DIVIPOL',
                        ], $now, $userName, $summary);
                    }
                    continue;
                }

                $remPermitidos = $this->getRemanentesPermitidosLocal($eleccionId, (string) $codpuesto);
                $remanentesAsignados = 0;
                $mesasDisponibles = $this->freeMesasLocal($eleccionId, (int) $puesto->id)->keyBy('id');

                $group = collect($group)->sortBy(function ($coord) {
                    return [$coord->eleccion_mesa_id ? 0 : 1, $coord->assigned_at ?? $coord->id, $coord->id];
                })->values();

                foreach ($group as $coordinacion) {
                    if ($coordinacion->eleccion_mesa_id) {
                        $mesaId = (int) $coordinacion->eleccion_mesa_id;
                        $mesaNum = optional($coordinacion->eleccionMesa)->mesa_num;
                        $mesasDisponibles->forget($mesaId);

                        $this->syncCoordinatorRecord($coordinacion, [
                            'tipo' => 'mesa',
                            'label' => 'Mesa ' . $mesaNum,
                            'eleccion_mesa_id' => $mesaId,
                            'validacion_estado' => $coordinacion->validacion_estado === 'validado' ? 'validado' : 'asignado',
                            'abogado_mesa' => (string) $mesaNum,
                            'note' => 'Integración rápida: conservado en mesa ' . $mesaNum,
                        ], $now, $userName, $summary);
                        continue;
                    }

                    if ($remanentesAsignados < $remPermitidos) {
                        $remanentesAsignados++;
                        $this->syncCoordinatorRecord($coordinacion, [
                            'tipo' => 'remanente',
                            'label' => 'Rem',
                            'eleccion_mesa_id' => null,
                            'validacion_estado' => $coordinacion->validacion_estado === 'validado' ? 'validado' : 'asignado',
                            'abogado_mesa' => 'Rem',
                            'note' => 'Integración rápida: clasificado como remanente',
                        ], $now, $userName, $summary);
                        continue;
                    }

                    $freeMesa = $mesasDisponibles->shift();
                    if ($freeMesa) {
                        $this->syncCoordinatorRecord($coordinacion, [
                            'tipo' => 'mesa',
                            'label' => 'Mesa ' . $freeMesa->mesa_num,
                            'eleccion_mesa_id' => (int) $freeMesa->id,
                            'validacion_estado' => $coordinacion->validacion_estado === 'validado' ? 'validado' : 'asignado',
                            'abogado_mesa' => (string) $freeMesa->mesa_num,
                            'note' => 'Integración rápida: movido a mesa ' . $freeMesa->mesa_num,
                        ], $now, $userName, $summary);
                        continue;
                    }

                    $this->syncCoordinatorRecord($coordinacion, [
                        'tipo' => 'sin_acreditar',
                        'label' => 'Sin acreditar',
                        'eleccion_mesa_id' => null,
                        'validacion_estado' => 'sin_acreditar',
                        'abogado_mesa' => 'Sin acreditar',
                        'note' => 'Integración rápida: sin cupo remanente ni mesa libre',
                    ], $now, $userName, $summary);
                }
            }
        });

        return $summary;
    }

    private function syncCoordinatorRecord(AbogadoCoordinacion $coordinacion, array $assignment, Carbon $now, string $userName, array &$summary): void
    {
        $originalMesaId = (int) ($coordinacion->eleccion_mesa_id ?? 0);
        $originalEstado = (string) ($coordinacion->validacion_estado ?? '');
        $originalAbogadoMesa = (string) optional($coordinacion->abogado)->mesa;

        $newMesaId = (int) ($assignment['eleccion_mesa_id'] ?? 0);
        $newEstado = (string) ($assignment['validacion_estado'] ?? 'asignado');
        $newAbogadoMesa = (string) ($assignment['abogado_mesa'] ?? 'Sin acreditar');

        if ($coordinacion->abogado) {
            $coordinacion->abogado->mesa = $newAbogadoMesa;
            $coordinacion->abogado->save();
        }

        $coordinacion->eleccion_mesa_id = $newMesaId ?: null;
        $coordinacion->validacion_estado = $newEstado;
        $coordinacion->observacion = $this->appendIntegrationNote(
            $coordinacion->observacion,
            $assignment['note'] . ' por ' . $userName . ' el ' . $now->format('Y-m-d H:i')
        );
        $coordinacion->save();

        if ($originalMesaId !== $newMesaId || $originalEstado !== $newEstado || $originalAbogadoMesa !== $newAbogadoMesa) {
            $summary['actualizados']++;
        }

        $summary[$assignment['tipo']]++;
    }

    private function appendIntegrationNote(?string $current, string $note): string
    {
        $current = trim((string) $current);
        return $current === '' ? $note : ($current . ' | ' . $note);
    }

    private function digitsOnlyLocal($value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?: 'coordinadores';
        return trim($value, '_');
    }
}
