<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abogado;
use App\Models\AbogadoCoordinacion;
use App\Models\Eleccion;
use App\Models\Puestos;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Traits\EleccionScope;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AbogadosController extends Controller
{
    use EleccionScope;

    private const PERSONAL_UPDATE_FIELDS = [
        'correo' => ['correo', 'email'],
        'telefono' => ['telefono', 'celular', 'telefono_celular'],
        'direccion' => ['direccion', 'dir', 'direccion_de_residencia'],
        'observacion' => ['observacion', 'obs'],
        'puesto' => ['puesto_donde_vota', 'puesto_de_votacion', 'puesto'],
        'mesa' => ['mesa'],
    ];

    public function index()
    {
        $abogados = Abogado::orderByDesc('activo')->orderBy('nombre')->get();
        $elecciones = Eleccion::query()
            ->where('estado', 'activa')
            ->orderByDesc('id')
            ->get(['id', 'nombre', 'tipo', 'estado']);
        $eleccionActiva = $this->resolveEleccionId();

        return view('admin.abogados.index', compact('abogados', 'elecciones', 'eleccionActiva'));
    }

    public function exportar()
    {
        $elecciones = Eleccion::query()
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'nombre', 'tipo', 'fecha']);

        $abogados = Abogado::query()
            ->orderBy('nombre')
            ->get();

        $coordinaciones = DB::table('abogado_coordinaciones as ac')
            ->leftJoin('eleccion_puestos as ep', function ($join) {
                $join->on('ep.eleccion_id', '=', 'ac.eleccion_id')
                    ->where(function ($query) {
                        $query->whereColumn('ep.codigo_puesto', 'ac.codpuesto')
                            ->orWhereRaw('CONCAT(ep.dd, ep.mm, ep.zz, ep.pp) = ac.codpuesto')
                            ->orWhere(function ($shortCode) {
                                $shortCode->whereRaw('CHAR_LENGTH(ac.codpuesto) <= 2')
                                    ->whereColumn('ep.pp', 'ac.codpuesto');
                            });
                    });
            })
            ->select(
                'ac.abogado_id',
                'ac.eleccion_id',
                DB::raw("TRIM(CONCAT_WS(' - ', ep.municipio, COALESCE(ep.puesto, ac.codpuesto))) as puesto_coordinado"),
                'ep.mm as codigo_municipio',
                'ep.zz as codigo_zona',
                'ep.pp as codigo_puesto_divipol',
                'ep.comuna as comuna_puesto',
                'ep.direccion as direccion_puesto'
            )
            ->get()
            ->groupBy('abogado_id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Abogados');

        $headers = [
            'ID',
            'Nombre',
            'Cédula',
            'Correo',
            'Teléfono',
            'Dirección',
            'Comuna manual del abogado',
            'Puesto donde vota',
            'Mesa',
            'Estudios',
            'Título',
            'Experiencia',
            'Experiencia electoral',
            'Rol',
            'Rol actual',
            'Funcionario',
            'Alcaldía',
            'Lugar',
            'Entidad',
            'Secretaría',
            'Honorarios',
            'Disponibilidad',
            'Fecha de inicio',
            'Facebook',
            'Instagram',
            'Observación',
            'Activo',
        ];

        foreach ($elecciones as $eleccion) {
            $headers[] = $eleccion->nombre . ' - Puesto coordinado';
            $headers[] = $eleccion->nombre . ' - COD';
            $headers[] = $eleccion->nombre . ' - ZON';
            $headers[] = $eleccion->nombre . ' - PTO';
            $headers[] = $eleccion->nombre . ' - Comuna oficial';
            $headers[] = $eleccion->nombre . ' - Dirección oficial';
        }

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex + 1, 1, $header);
        }

        foreach ($abogados as $rowIndex => $abogado) {
            $row = [
                $abogado->id,
                $abogado->nombre,
                $abogado->cc,
                $abogado->correo,
                $abogado->telefono,
                $abogado->direccion,
                $abogado->comuna,
                $abogado->puesto,
                $abogado->mesa,
                $abogado->estudios,
                $abogado->titulo,
                $abogado->experiencia,
                $abogado->electoral,
                $abogado->rol,
                $abogado->rol_actual,
                $abogado->funcionario,
                $abogado->alcaldia,
                $abogado->lugar,
                $abogado->entidad,
                $abogado->secretaria,
                $abogado->honorarios,
                $abogado->disponibilidad,
                $abogado->fecha_inicio,
                $abogado->face,
                $abogado->insta,
                $abogado->observacion,
                $abogado->activo ? 'Sí' : 'No',
            ];

            $coordinacionesAbogado = $coordinaciones->get($abogado->id, collect());

            foreach ($elecciones as $eleccion) {
                $participaciones = $coordinacionesAbogado->where('eleccion_id', $eleccion->id);

                $row[] = $participaciones->pluck('puesto_coordinado')->filter()->unique()->implode(' | ');
                $row[] = $participaciones->pluck('codigo_municipio')->filter()->unique()->implode(' | ');
                $row[] = $participaciones->pluck('codigo_zona')->filter()->unique()->implode(' | ');
                $row[] = $participaciones->pluck('codigo_puesto_divipol')->filter()->unique()->implode(' | ');
                $row[] = $participaciones->pluck('comuna_puesto')->filter()->unique()->implode(' | ');
                $row[] = $participaciones->pluck('direccion_puesto')->filter()->unique()->implode(' | ');
            }

            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueExplicitByColumnAndRow(
                    $columnIndex + 1,
                    $rowIndex + 2,
                    (string) ($value ?? ''),
                    DataType::TYPE_STRING
                );
            }
        }

        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $headerRange = "A1:{$lastColumn}1";

        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getRowDimension(1)->setRowHeight(30);

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $filename = 'listado_abogados_por_campania_' . now()->format('Y-m-d_His') . '.xlsx';
        $temporaryFile = tempnam(sys_get_temp_dir(), 'abogados_');
        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temporaryFile, $filename)->deleteFileAfterSend(true);
    }

    public function previewActualizacionPersonal(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        try {
            $rows = $this->rowsFromUploadedFile($request->file('archivo'));
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo: ' . $e->getMessage()]);
        }

        $headerRowIndex = $this->findHeaderRowIndex($rows, ['cedula']);
        if ($headerRowIndex === null) {
            return back()->withErrors(['archivo' => 'El archivo debe incluir una columna Cédula.']);
        }

        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $rows[$headerRowIndex]);
        $idx = array_flip($header);
        $source = [];
        $duplicates = [];

        foreach (array_slice($rows, $headerRowIndex + 1) as $offset => $row) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $cedula = $this->digitsOnly($this->valueByAliases($row, $idx, ['cedula', 'cc', 'documento']));
            if ($cedula === '') {
                continue;
            }

            if (isset($source[$cedula])) {
                $duplicates[$cedula] = true;
                continue;
            }

            $fields = [];
            foreach (self::PERSONAL_UPDATE_FIELDS as $field => $aliases) {
                $fields[$field] = trim((string) $this->valueByAliases($row, $idx, $aliases));
            }

            $source[$cedula] = [
                'row' => $headerRowIndex + 2 + $offset,
                'cedula' => $cedula,
                'nombre' => trim((string) $this->valueByAliases($row, $idx, ['nombre', 'nombre_completo'])),
                'fields' => $fields,
            ];
        }

        $abogados = Abogado::whereIn('cc', array_keys($source))
            ->get()
            ->groupBy(fn ($abogado) => $this->digitsOnly($abogado->cc));
        $coordinationLabels = $this->activeCoordinationLabels();
        $changes = [];
        $counts = [
            'rows' => count($source),
            'safe' => 0,
            'conflicts' => 0,
            'not_found' => 0,
            'duplicates' => count($duplicates),
        ];

        foreach ($source as $cedula => $item) {
            if (isset($duplicates[$cedula])) {
                $changes[] = $this->personalChangeRow($item, null, null, null, 'duplicado', 'La cédula aparece más de una vez en el archivo.');
                continue;
            }

            $matches = $abogados->get($cedula, collect());
            if ($matches->count() !== 1) {
                $counts['not_found']++;
                $message = $matches->isEmpty()
                    ? 'La cédula no existe en la caracterización.'
                    : 'La cédula está duplicada en la base de datos.';
                $changes[] = $this->personalChangeRow($item, null, null, null, 'no_encontrado', $message);
                continue;
            }

            $abogado = $matches->first();
            foreach ($item['fields'] as $field => $newValue) {
                if (!$this->isMeaningfulImportValue($newValue)) {
                    continue;
                }

                $currentValue = trim((string) ($abogado->{$field} ?? ''));
                if ($this->normalizePersonalValue($currentValue) === $this->normalizePersonalValue($newValue)) {
                    continue;
                }

                $safe = $this->isSafePersonalRestore(
                    $field,
                    $currentValue,
                    $coordinationLabels[$abogado->id] ?? []
                );
                $status = $safe ? 'restaurar' : 'conflicto';
                $counts[$safe ? 'safe' : 'conflicts']++;
                $changes[] = $this->personalChangeRow(
                    $item,
                    $abogado,
                    $field,
                    $newValue,
                    $status,
                    $safe
                        ? 'El valor actual tiene la huella de la importación anterior.'
                        : 'El valor actual parece válido o editado; no será reemplazado.'
                );
            }
        }

        foreach ($changes as $index => &$change) {
            $change['change_id'] = in_array($change['status'], ['restaurar', 'conflicto'], true)
                ? 'change_' . $index
                : null;
        }
        unset($change);

        $token = Str::random(40);
        $payload = [
            'created_at' => now()->toIso8601String(),
            'created_by' => auth()->id(),
            'changes' => collect($changes)
                ->filter(fn ($change) => $change['change_id'] !== null)
                ->keyBy('change_id')
                ->all(),
        ];
        Storage::disk('local')->put(
            'actualizaciones-personales/' . $token . '.json',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return view('admin.abogados.personal_update_preview', compact('changes', 'counts', 'token'));
    }

    public function applyActualizacionPersonal(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|size:40',
            'selected_changes' => 'required|array|min:1',
            'selected_changes.*' => 'required|string|max:50',
        ]);
        $path = 'actualizaciones-personales/' . $data['token'] . '.json';
        if (!Storage::disk('local')->exists($path)) {
            return redirect()->route('admin.abogados.index')
                ->withErrors(['archivo' => 'La previsualización no existe o ya fue aplicada.']);
        }

        $payload = json_decode(Storage::disk('local')->get($path), true);
        if (!is_array($payload) || (int) ($payload['created_by'] ?? 0) !== (int) auth()->id()) {
            abort(403);
        }
        if (empty($payload['created_at']) || Carbon::parse($payload['created_at'])->lt(now()->subHours(2))) {
            Storage::disk('local')->delete($path);
            return redirect()->route('admin.abogados.index')
                ->withErrors(['archivo' => 'La previsualización venció. Carga nuevamente el archivo.']);
        }

        $applied = 0;
        $skipped = 0;
        $coordBefore = $this->coordinationFingerprint();
        $selectedChanges = collect($data['selected_changes'])->unique()->values();

        DB::transaction(function () use ($payload, $selectedChanges, &$applied, &$skipped) {
            foreach ($selectedChanges as $changeId) {
                $change = $payload['changes'][$changeId] ?? null;
                if (!$change) {
                    $skipped++;
                    continue;
                }

                $abogado = Abogado::whereKey((int) $change['abogado_id'])->lockForUpdate()->first();
                if (!$abogado || !array_key_exists($change['field'], self::PERSONAL_UPDATE_FIELDS)) {
                    $skipped++;
                    continue;
                }

                if ($this->normalizePersonalValue($abogado->{$change['field']}) !== $this->normalizePersonalValue($change['current'])) {
                    $skipped++;
                    continue;
                }

                $abogado->{$change['field']} = $change['new'];
                $abogado->save();
                $applied++;
            }
        });

        if ($coordBefore !== $this->coordinationFingerprint()) {
            throw new \RuntimeException('La huella de coordinaciones cambió durante la actualización.');
        }

        Storage::disk('local')->delete($path);

        return redirect()->route('admin.abogados.index')
            ->with('info', "Actualización personal terminada. Campos restaurados: {$applied}. Omitidos por cambios posteriores: {$skipped}. Coordinaciones: sin cambios.");
    }

    public function create()
    {
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

        return view('admin.abogados.create', compact('puestos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cc' => 'required|string|max:50',
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
            'disponibilidad' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:4096',
            'pdf_cc' => 'nullable|file|mimes:pdf|max:4096',
            'activo' => 'nullable|boolean',
        ]);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('abogados/fotos', 'public');
        }
        if ($request->hasFile('pdf_cc')) {
            $data['pdf_cc'] = $request->file('pdf_cc')->store('abogados/cc', 'public');
        }
        $data['activo'] = (bool) ($data['activo'] ?? true);
        Abogado::create($data);

        return redirect()->route('admin.abogados.index')->with('info', 'Abogado creado correctamente.');
    }

    public function show(Abogado $abogado)
    {
        $abogado->load(['reuniones' => function ($q) {
            $q->orderByDesc('fecha');
        }]);
        [$municipioVota, $puestoVota] = $this->splitPuestoTexto($abogado->puesto);
        $puestosMunicipio = collect();

        if ($municipioVota) {
            $eleccionActivaId = $this->resolveEleccionId();
            $puestosMunicipio = DB::table('eleccion_puestos')
                ->where('eleccion_id', $eleccionActivaId)
                ->whereRaw('UPPER(TRIM(municipio)) = ?', [mb_strtoupper(trim((string) $municipioVota), 'UTF-8')])
                ->orderBy('dd')
                ->orderBy('mm')
                ->orderBy('zz')
                ->orderBy('pp')
                ->get()
                ->map(function ($p) use ($puestoVota, $eleccionActivaId) {
                    $codpuesto = $this->buildEleccionPuestoKey($p);
                    $totalRem = $this->getRemanentesPermitidos((int) $eleccionActivaId, $codpuesto);
                    $ocupadosRem = AbogadoCoordinacion::where('eleccion_id', $eleccionActivaId)
                        ->where('codpuesto', $codpuesto)
                        ->whereNull('released_at')
                        ->count();

                    return (object) [
                        'codpuesto' => $codpuesto,
                        'label' => trim(($p->municipio ?? '') . ' - ' . ($p->puesto ?? ''), ' -'),
                        'total_rem' => $totalRem,
                        'ocupados_rem' => $ocupadosRem,
                        'disponibles_rem' => max(0, $totalRem - $ocupadosRem),
                        'es_puesto_vota' => mb_strtoupper(trim((string) $p->puesto), 'UTF-8') === mb_strtoupper(trim((string) $puestoVota), 'UTF-8'),
                    ];
                })
                ->values();
        }

        $elecciones = Eleccion::query()
            ->where('estado', 'activa')
            ->orderByDesc('id')
            ->get(['id', 'nombre', 'tipo', 'estado']);
        $eleccionActiva = $this->resolveEleccionId();
        $historialCoordinaciones = AbogadoCoordinacion::query()
            ->from('abogado_coordinaciones as ac')
            ->leftJoin('elecciones as e', 'e.id', '=', 'ac.eleccion_id')
            ->leftJoin('puestos as p', 'p.codpuesto', '=', 'ac.codpuesto')
            ->leftJoin('eleccion_puestos as ep', function ($join) {
                $join->on('ep.eleccion_id', '=', 'ac.eleccion_id')
                    ->where(function ($q) {
                        $q->whereColumn('ep.codigo_puesto', 'ac.codpuesto')
                            ->orWhereRaw('CONCAT(ep.dd,ep.mm,ep.zz,ep.pp) = ac.codpuesto')
                            ->orWhere(function ($shortCode) {
                                $shortCode->whereRaw('CHAR_LENGTH(ac.codpuesto) <= 2')
                                    ->whereColumn('ep.pp', 'ac.codpuesto');
                            });
                    });
            })
            ->where('ac.abogado_id', $abogado->id)
            ->orderByDesc('ac.assigned_at')
            ->select(
                'ac.*',
                'e.nombre as eleccion_nombre',
                'e.tipo as eleccion_tipo',
                DB::raw('COALESCE(ep.municipio, p.mun) as puesto_municipio'),
                DB::raw('COALESCE(ep.puesto, p.nombre) as puesto_nombre'),
                'ep.comuna as puesto_comuna',
                'ep.direccion as puesto_direccion'
            )
            ->get();

        return view('admin.abogados.show', compact(
            'abogado',
            'puestosMunicipio',
            'municipioVota',
            'elecciones',
            'eleccionActiva',
            'historialCoordinaciones'
        ));
    }

    public function edit(Abogado $abogado)
    {
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

        return view('admin.abogados.edit', compact('abogado', 'puestos'));
    }

    public function update(Request $request, Abogado $abogado)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cc' => 'required|string|max:50',
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
            'disponibilidad' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:4096',
            'pdf_cc' => 'nullable|file|mimes:pdf|max:4096',
            'activo' => 'nullable|boolean',
        ]);
        if ($request->hasFile('foto')) {
            if ($abogado->foto) {
                Storage::disk('public')->delete($abogado->foto);
            }
            $data['foto'] = $request->file('foto')->store('abogados/fotos', 'public');
        }
        if ($request->hasFile('pdf_cc')) {
            if ($abogado->pdf_cc) {
                Storage::disk('public')->delete($abogado->pdf_cc);
            }
            $data['pdf_cc'] = $request->file('pdf_cc')->store('abogados/cc', 'public');
        }
        $data['activo'] = (bool) ($data['activo'] ?? false);
        $abogado->update($data);

        return redirect()->route('admin.abogados.index')->with('info', 'Abogado actualizado correctamente.');
    }

    public function destroy(Abogado $abogado)
    {
        $abogado->delete();
        return redirect()->route('admin.abogados.index')->with('info', 'Abogado eliminado correctamente.');
    }

    public function importFromSheet(Request $request)
    {
        $data = $request->validate([
            'spreadsheet_url' => 'nullable|url',
            'gid' => 'nullable|numeric',
            'csv_file' => 'nullable|file|mimes:csv,txt|max:5120',
        ]);

        if (!$request->hasFile('csv_file') && empty($data['spreadsheet_url'])) {
            return back()->withErrors(['spreadsheet_url' => 'Debes enviar URL de Google Sheets o archivo CSV.']);
        }

        if ($request->hasFile('csv_file')) {
            $raw = file_get_contents($request->file('csv_file')->getRealPath());
            $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim((string) $raw)));
        } else {
            $sheetId = $this->extractSheetId((string) $data['spreadsheet_url']);
            if (!$sheetId) {
                return back()->withErrors(['spreadsheet_url' => 'URL de Google Sheets no válida.']);
            }

            $gid = isset($data['gid']) && $data['gid'] !== '' ? (string) $data['gid'] : '0';
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
            $resp = Http::timeout(45)->get($csvUrl);
            if (!$resp->ok()) {
                return back()->withErrors(['spreadsheet_url' => 'No se pudo descargar la hoja. Usa CSV si el servidor bloquea Google.']);
            }
            $rows = array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim((string) $resp->body())));
        }
        if (count($rows) < 2) {
            return back()->withErrors(['spreadsheet_url' => 'La hoja no tiene datos para importar.']);
        }

        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $rows[0]);
        $idx = array_flip($header);

        $created = 0;
        $updated = 0;
        foreach (array_slice($rows, 1) as $row) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $cc = trim((string) $this->valueByAliases($row, $idx, ['cedula', 'cedula_de_ciudadania', 'cc']));
            $nombre = trim((string) $this->valueByAliases($row, $idx, ['nombre_completo', 'nombre']));
            if ($cc === '' || $nombre === '') {
                continue;
            }

            $puestoTexto = trim((string) $this->valueByAliases($row, $idx, [
                'puesto_de_votacion',
                'puesto',
                'puesto_de_votacion_',
            ]));
            $puestoEstructurado = $this->normalizePuestoForSelect($puestoTexto);

            $incomingCorreo = trim((string) $this->valueByAliases($row, $idx, ['correo', 'email']));
            $incomingDireccion = trim((string) $this->valueByAliases($row, $idx, ['direccion', 'direccion_de_residencia']));
            $incomingMesa = trim((string) $this->valueByAliases($row, $idx, ['mesa']));
            $payload = [
                'nombre' => $nombre,
                'cc' => $cc,
                'correo' => $incomingCorreo ?: ($cc . '@sin-correo.local'),
                'direccion' => $incomingDireccion ?: 'N/D',
                'telefono' => 'N/D',
                'comuna' => 'N/D',
                'puesto' => $puestoEstructurado ?: ($puestoTexto ?: 'N/D'),
                'mesa' => $incomingMesa ?: 'N/D',
                'activo' => true,
            ];

            $pdfLink = trim((string) $this->valueByAliases($row, $idx, [
                'pdf_de_la_cedula',
                'pdf_cedula',
            ]));
            $fotoLink = trim((string) $this->valueByAliases($row, $idx, [
                'cargar_foto',
                'cargar_foto_',
                'foto',
            ]));
            $obsParts = [];
            if ($pdfLink !== '') {
                $obsParts[] = 'PDF origen: ' . $pdfLink;
            }
            if ($fotoLink !== '') {
                $obsParts[] = 'FOTO origen: ' . $fotoLink;
            }
            if (!empty($obsParts)) {
                $payload['observacion'] = implode(' | ', $obsParts);
            }

            $abogado = Abogado::where('cc', $cc)->first();
            if ($abogado) {
                $safeUpdate = [
                    'nombre' => $nombre,
                    'activo' => true,
                ];
                if ($this->isMeaningfulImportValue($incomingCorreo)) {
                    $safeUpdate['correo'] = $incomingCorreo;
                }
                if ($this->isMeaningfulImportValue($incomingDireccion)) {
                    $safeUpdate['direccion'] = $incomingDireccion;
                }
                if ($this->isMeaningfulImportValue($incomingMesa)) {
                    $safeUpdate['mesa'] = $incomingMesa;
                }
                if (!empty($obsParts)) {
                    $safeUpdate['observacion'] = implode(' | ', $obsParts);
                }
                $abogado->fill($safeUpdate)->save();
                $updated++;
            } else {
                Abogado::create($payload);
                $created++;
            }
        }

        return back()->with('info', "Importación completada. Nuevos: {$created}. Actualizados: {$updated}.");
    }

    public function asignarCoordinador(Request $request, Abogado $abogado)
    {
        $data = $request->validate([
            'codpuesto' => 'required|string',
            'eleccion_id' => 'required|integer|exists:elecciones,id',
        ]);

        $eleccion = Eleccion::where('id', $data['eleccion_id'])
            ->where('estado', 'activa')
            ->first();
        if (!$eleccion) {
            return back()->withErrors(['eleccion_id' => 'Solo puedes asignar coordinadores en una elección activa.']);
        }

        [$municipioVota] = $this->splitPuestoTexto($abogado->puesto);
        $puesto = $this->findEleccionPuestoByCodigo((int) $data['eleccion_id'], (string) $data['codpuesto']);
        if (!$puesto) {
            return back()->withErrors(['codpuesto' => 'El puesto seleccionado no existe en la DIVIPOL de la elección activa.']);
        }

        if ($municipioVota && mb_strtoupper(trim((string) $puesto->municipio), 'UTF-8') !== mb_strtoupper(trim((string) $municipioVota), 'UTF-8')) {
            return back()->withErrors(['codpuesto' => 'Solo puedes asignar en el municipio donde vota.']);
        }

        try {
            DB::transaction(function () use ($abogado, $puesto, $data) {
                $codpuesto = $this->buildEleccionPuestoKey($puesto);
                $yaAsignado = AbogadoCoordinacion::where('abogado_id', $abogado->id)
                    ->where('eleccion_id', $data['eleccion_id'])
                    ->whereNull('released_at')
                    ->exists();
                if ($yaAsignado) {
                    throw new \RuntimeException('Esta persona ya tiene una coordinación activa en esta elección.');
                }

                $totalRem = $this->getRemanentesPermitidos((int) $data['eleccion_id'], $codpuesto);
                $ocupadosRem = AbogadoCoordinacion::where('eleccion_id', $data['eleccion_id'])
                    ->where('codpuesto', $codpuesto)
                    ->whereNull('released_at')
                    ->lockForUpdate()
                    ->count();

                if ($totalRem <= 0 || $ocupadosRem >= $totalRem) {
                    throw new \RuntimeException('No hay cupos remanentes disponibles en este puesto.');
                }

                AbogadoCoordinacion::create([
                    'abogado_id' => $abogado->id,
                    'eleccion_id' => $data['eleccion_id'],
                    'codpuesto' => $codpuesto,
                    'seller_id' => null,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => Carbon::now('America/Bogota'),
                    'validacion_estado' => 'asignado',
                    'observacion' => 'Asignado a remanente desde caracterización abogados',
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['codpuesto' => $e->getMessage()]);
        }

        return back()->with('info', 'Coordinador asignado correctamente a cupo remanente.');
    }

    public function liberarCoordinador(Abogado $abogado, AbogadoCoordinacion $coordinacion)
    {
        if ((int) $coordinacion->abogado_id !== (int) $abogado->id) {
            abort(404);
        }

        if ($coordinacion->released_at) {
            return back()->with('info', 'Esta coordinación ya estaba liberada.');
        }

        DB::transaction(function () use ($coordinacion) {
            $coordinacion = AbogadoCoordinacion::whereKey($coordinacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($coordinacion->released_at) {
                return;
            }

            $nota = 'Liberado por ' . (optional(auth()->user())->name ?? 'administrador')
                . ' el ' . Carbon::now('America/Bogota')->format('Y-m-d H:i');

            $coordinacion->released_at = Carbon::now('America/Bogota');
            $coordinacion->validacion_estado = 'liberado';
            $coordinacion->observacion = trim(
                implode(' | ', array_filter([$coordinacion->observacion, $nota]))
            );
            $coordinacion->save();
        });

        return back()->with('info', 'Coordinador retirado. El cupo remanente quedó disponible nuevamente.');
    }

    public function importCoordinadores(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'archivo' => 'nullable|file|mimes:xlsx,xls,csv,txt|max:10240',
            'spreadsheet_url' => 'nullable|url',
            'gid' => 'nullable|string|max:50',
        ]);

        if (!$request->hasFile('archivo') && empty($data['spreadsheet_url'])) {
            return back()->withErrors(['archivo' => 'Debes subir Excel/CSV o enviar URL de Google Sheets.']);
        }

        $eleccion = Eleccion::where('id', $data['eleccion_id'])
            ->where('estado', 'activa')
            ->first();
        if (!$eleccion) {
            return back()->withErrors(['eleccion_id' => 'Solo puedes importar coordinadores en una elección activa.']);
        }

        try {
            $rows = $request->hasFile('archivo')
                ? $this->rowsFromUploadedFile($request->file('archivo'))
                : $this->rowsFromGoogleSheet((string) $data['spreadsheet_url'], (string) ($data['gid'] ?? '0'));
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo: ' . $e->getMessage()]);
        }

        if (count($rows) < 2) {
            return back()->withErrors(['archivo' => 'El archivo no tiene datos para importar.']);
        }

        $headerRowIndex = $this->findHeaderRowIndex($rows, ['cedula', 'puesto']);
        if ($headerRowIndex === null) {
            return back()->withErrors(['archivo' => 'No encontré encabezados válidos. Debe incluir al menos CEDULA y PUESTO.']);
        }

        $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $rows[$headerRowIndex]);
        $idx = array_flip($header);

        $createdAbogados = 0;
        $updatedAbogados = 0;
        $createdCoordinaciones = 0;
        $omitted = 0;
        $errors = [];

        foreach (array_slice($rows, $headerRowIndex + 1) as $offset => $row) {
            $fila = $headerRowIndex + 2 + $offset;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $cc = $this->digitsOnly($this->valueByAliases($row, $idx, ['cedula', 'cc', 'documento', 'documento_de_identificacion']));
            $nombre = trim((string) $this->valueByAliases($row, $idx, ['nombre', 'nombre_completo']));
            $puestoTexto = trim((string) $this->valueByAliases($row, $idx, ['puesto', 'puesto_de_votacion', 'puesto_votacion']));

            if ($cc === '' || $nombre === '' || $puestoTexto === '') {
                $errors[] = "Fila {$fila}: falta nombre, cédula o puesto.";
                continue;
            }

            $puesto = $this->findEleccionPuestoByTexto((int) $eleccion->id, $puestoTexto);
            if (!$puesto) {
                $errors[] = "Fila {$fila}: puesto no encontrado o ambiguo \"{$puestoTexto}\".";
                continue;
            }

            $codpuesto = $this->buildEleccionPuestoKey($puesto);

            try {
                DB::transaction(function () use (
                    $row,
                    $idx,
                    $eleccion,
                    $cc,
                    $nombre,
                    $puesto,
                    $codpuesto,
                    $puestoTexto,
                    &$createdAbogados,
                    &$updatedAbogados,
                    &$createdCoordinaciones,
                    &$omitted
                ) {
                    $abogado = Abogado::where('cc', $cc)->lockForUpdate()->first();
                    $incomingCorreo = trim((string) $this->valueByAliases($row, $idx, ['correo', 'email']));
                    $incomingDireccion = trim((string) $this->valueByAliases($row, $idx, ['dir', 'direccion', 'direccion_de_residencia']));
                    $incomingTelefono = trim((string) $this->valueByAliases($row, $idx, ['telefono', 'celular', 'telefono_celular']));
                    $incomingObservacion = trim((string) $this->valueByAliases($row, $idx, ['observacion', 'obs']));
                    $payload = [
                        'nombre' => $nombre,
                        'cc' => $cc,
                        'correo' => $incomingCorreo ?: ($cc . '@sin-correo.local'),
                        'direccion' => $incomingDireccion ?: 'N/D',
                        'telefono' => $incomingTelefono ?: 'N/D',
                        'comuna' => trim((string) ($puesto->comuna ?? 'N/D')) ?: 'N/D',
                        'puesto' => trim(($puesto->municipio ?? '') . ' - ' . ($puesto->puesto ?? $puestoTexto), ' -'),
                        'mesa' => 'Rem',
                        'observacion' => $incomingObservacion ?: null,
                        'activo' => true,
                    ];

                    if ($abogado) {
                        $safeUpdate = [
                            'nombre' => $nombre,
                            'activo' => true,
                        ];
                        if ($this->isMeaningfulImportValue($incomingCorreo)) {
                            $safeUpdate['correo'] = $incomingCorreo;
                        }
                        if ($this->isMeaningfulImportValue($incomingDireccion)) {
                            $safeUpdate['direccion'] = $incomingDireccion;
                        }
                        if ($this->isMeaningfulImportValue($incomingTelefono)) {
                            $safeUpdate['telefono'] = $incomingTelefono;
                        }
                        if ($this->isMeaningfulImportValue($incomingObservacion)) {
                            $safeUpdate['observacion'] = $incomingObservacion;
                        }
                        $abogado->fill($safeUpdate)->save();
                        $updatedAbogados++;
                    } else {
                        $abogado = Abogado::create($payload);
                        $createdAbogados++;
                    }

                    $existing = AbogadoCoordinacion::where('abogado_id', $abogado->id)
                        ->where('eleccion_id', $eleccion->id)
                        ->whereNull('released_at')
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        if ((string) $existing->codpuesto === (string) $codpuesto) {
                            $omitted++;
                            return;
                        }

                        throw new \RuntimeException('ya tiene otra coordinación activa en esta elección');
                    }

                    $totalRem = $this->getRemanentesPermitidos((int) $eleccion->id, $codpuesto);
                    $ocupadosRem = AbogadoCoordinacion::where('eleccion_id', $eleccion->id)
                        ->where('codpuesto', $codpuesto)
                        ->whereNull('released_at')
                        ->lockForUpdate()
                        ->count();

                    if ($totalRem <= 0 || $ocupadosRem >= $totalRem) {
                        throw new \RuntimeException('no hay cupo remanente disponible');
                    }

                    AbogadoCoordinacion::create([
                        'abogado_id' => $abogado->id,
                        'eleccion_id' => $eleccion->id,
                        'codpuesto' => $codpuesto,
                        'seller_id' => null,
                        'assigned_by' => auth()->id(),
                        'assigned_at' => Carbon::now('America/Bogota'),
                        'validacion_estado' => 'asignado',
                        'observacion' => 'Carga masiva coordinadores: ' . $puestoTexto,
                    ]);

                    $createdCoordinaciones++;
                });
            } catch (\Throwable $e) {
                $errors[] = "Fila {$fila}: {$nombre} ({$cc}) - {$e->getMessage()}.";
            }
        }

        $message = "Importación coordinadores: asignados {$createdCoordinaciones}. Abogados nuevos {$createdAbogados}. Actualizados {$updatedAbogados}. Omitidos {$omitted}. Errores " . count($errors) . ".";

        return back()
            ->with('info', $message)
            ->with('coordinadores_import_errors', array_slice($errors, 0, 80));
    }

    private function buildEleccionPuestoKey(object $puesto): string
    {
        $codigo = trim((string) ($puesto->codigo_puesto ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        return (string) ($puesto->dd ?? '') . (string) ($puesto->mm ?? '') . (string) ($puesto->zz ?? '') . (string) ($puesto->pp ?? '');
    }

    private function findEleccionPuestoByCodigo(int $eleccionId, string $codpuesto): ?object
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

    private function getRemanentesPermitidos(int $eleccionId, string $codpuesto): int
    {
        if (!$eleccionId || $codpuesto === '') {
            return 0;
        }

        $puesto = $this->findEleccionPuestoByCodigo($eleccionId, $codpuesto);

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

    private function findEleccionPuestoByTexto(int $eleccionId, string $puestoTexto): ?object
    {
        $needle = $this->normalizeSearchText($puestoTexto);
        if ($needle === '') {
            return null;
        }

        $puestos = DB::table('eleccion_puestos')
            ->where('eleccion_id', $eleccionId)
            ->get();

        $matches = [];
        foreach ($puestos as $puesto) {
            $codpuesto = $this->buildEleccionPuestoKey($puesto);
            if ($needle === $this->normalizeSearchText($codpuesto)) {
                return $puesto;
            }

            $haystack = $this->normalizeSearchText(trim(($puesto->municipio ?? '') . ' ' . ($puesto->puesto ?? '')));
            $puestoOnly = $this->normalizeSearchText((string) ($puesto->puesto ?? ''));

            $score = 0;
            if ($needle === $puestoOnly || $needle === $haystack) {
                $score = 100;
            } elseif (strpos($puestoOnly, $needle) !== false || strpos($needle, $puestoOnly) !== false) {
                $score = 92;
            } else {
                similar_text($needle, $puestoOnly, $similarity);
                $score = (float) $similarity;
                $tokensNeedle = array_filter(explode(' ', $needle));
                $tokensPuesto = array_filter(explode(' ', $puestoOnly));
                if (!empty($tokensNeedle) && !empty($tokensPuesto)) {
                    $overlap = count(array_intersect($tokensNeedle, $tokensPuesto)) / count($tokensNeedle);
                    $score = max($score, $overlap * 100);
                }
            }

            if ($score >= 62) {
                $matches[] = ['score' => $score, 'puesto' => $puesto];
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);
        if (empty($matches)) {
            return null;
        }

        if (count($matches) > 1 && abs($matches[0]['score'] - $matches[1]['score']) < 3) {
            return null;
        }

        return $matches[0]['puesto'];
    }

    private function rowsFromUploadedFile($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['csv', 'txt'], true)) {
            $raw = file_get_contents($file->getRealPath());
            return array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim((string) $raw)));
        }

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            throw new \RuntimeException('PhpSpreadsheet no está instalado.');
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Remanentes') ?: $spreadsheet->getActiveSheet();
        return $sheet->toArray(null, true, true, false);
    }

    private function rowsFromGoogleSheet(string $url, string $gid = '0'): array
    {
        $sheetId = $this->extractSheetId($url);
        if (!$sheetId) {
            throw new \RuntimeException('URL de Google Sheets no válida.');
        }

        $gid = $gid !== '' ? $gid : '0';
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
        $resp = Http::timeout(45)->get($csvUrl);
        if (!$resp->ok()) {
            throw new \RuntimeException('No se pudo descargar la hoja. Revisa permisos o sube Excel/CSV.');
        }

        return array_map('str_getcsv', preg_split("/\r\n|\n|\r/", trim((string) $resp->body())));
    }

    private function findHeaderRowIndex(array $rows, array $requiredAliases): ?int
    {
        foreach (array_slice($rows, 0, 20, true) as $i => $row) {
            $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $row);
            $found = 0;
            foreach ($requiredAliases as $alias) {
                if (in_array($alias, $header, true)) {
                    $found++;
                }
            }
            if ($found === count($requiredAliases)) {
                return (int) $i;
            }
        }

        return null;
    }

    private function digitsOnly($value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normalizeSearchText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($trans !== false) {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function splitPuestoTexto(?string $puesto): array
    {
        $texto = trim((string) $puesto);
        if ($texto === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*-\s*/', $texto, 2);
        if (count($parts) < 2) {
            return [$texto, null];
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    private function extractSheetId(string $url): ?string
    {
        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9-_]+)~', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($trans !== false) {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function valueByAliases(array $row, array $idx, array $aliases): string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $idx)) {
                return (string) ($row[$idx[$alias]] ?? '');
            }
        }
        return '';
    }

    private function isMeaningfulImportValue($value): bool
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strtoupper($value, 'UTF-8') === 'N/D') {
            return false;
        }

        return substr(mb_strtolower($value, 'UTF-8'), -17) !== '@sin-correo.local';
    }

    private function isSafePersonalRestore(string $field, string $currentValue, array $coordinationLabels): bool
    {
        $current = $this->normalizePersonalValue($currentValue);
        if ($current === '' || $current === 'n/d') {
            return true;
        }
        if ($field === 'correo') {
            return substr($current, -17) === '@sin-correo.local';
        }
        if ($field === 'mesa') {
            return $current === 'rem';
        }
        if ($field === 'puesto') {
            foreach ($coordinationLabels as $label) {
                if ($current === $this->normalizePersonalValue($label)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizePersonalValue($value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower(trim($value), 'UTF-8');
    }

    private function activeCoordinationLabels(): array
    {
        $rows = DB::table('abogado_coordinaciones as ac')
            ->leftJoin('eleccion_puestos as ep', function ($join) {
                $join->on('ep.eleccion_id', '=', 'ac.eleccion_id')
                    ->where(function ($query) {
                        $query->whereColumn('ep.codigo_puesto', 'ac.codpuesto')
                            ->orWhereRaw('CONCAT(ep.dd,ep.mm,ep.zz,ep.pp) = ac.codpuesto');
                    });
            })
            ->whereNull('ac.released_at')
            ->select('ac.abogado_id', 'ep.municipio', 'ep.puesto')
            ->get();

        $labels = [];
        foreach ($rows as $row) {
            $label = trim(($row->municipio ?? '') . ' - ' . ($row->puesto ?? ''), ' -');
            if ($label !== '') {
                $labels[$row->abogado_id][] = $label;
            }
        }
        return $labels;
    }

    private function personalChangeRow(array $item, $abogado, ?string $field, ?string $newValue, string $status, string $message): array
    {
        return [
            'row' => $item['row'],
            'cedula' => $item['cedula'],
            'nombre' => $item['nombre'] ?: ($abogado->nombre ?? '-'),
            'abogado_id' => $abogado->id ?? null,
            'field' => $field,
            'current' => $field && $abogado ? (string) ($abogado->{$field} ?? '') : null,
            'new' => $newValue,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function coordinationFingerprint(): string
    {
        return hash('sha256', DB::table('abogado_coordinaciones')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => json_encode((array) $row, JSON_UNESCAPED_UNICODE))
            ->implode("\n"));
    }

    private function normalizePuestoForSelect(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Si ya viene con la estructura actual (MUNICIPIO - PUESTO), se conserva.
        if (strpos($raw, ' - ') !== false) {
            return $raw;
        }

        // Busca el puesto por nombre para guardar con la estructura del select actual.
        $puesto = Puestos::query()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($raw)])
            ->orderBy('mun')
            ->first();

        if (!$puesto) {
            $puesto = Puestos::query()
                ->where('nombre', 'like', '%' . $raw . '%')
                ->orderBy('mun')
                ->first();
        }

        if (!$puesto) {
            return null;
        }

        return trim(($puesto->mun ?? '') . ' - ' . ($puesto->nombre ?? $raw), ' -');
    }
}
