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
use Carbon\Carbon;
use App\Traits\EleccionScope;

class AbogadosController extends Controller
{
    use EleccionScope;

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
                            ->orWhereColumn('ep.pp', 'ac.codpuesto');
                    });
            })
            ->where('ac.abogado_id', $abogado->id)
            ->orderByDesc('ac.assigned_at')
            ->select(
                'ac.*',
                'e.nombre as eleccion_nombre',
                'e.tipo as eleccion_tipo',
                DB::raw('COALESCE(ep.municipio, p.mun) as puesto_municipio'),
                DB::raw('COALESCE(ep.puesto, p.nombre) as puesto_nombre')
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

            $payload = [
                'nombre' => $nombre,
                'cc' => $cc,
                'correo' => trim((string) $this->valueByAliases($row, $idx, ['correo', 'email'])) ?: ($cc . '@sin-correo.local'),
                'direccion' => trim((string) $this->valueByAliases($row, $idx, ['direccion', 'direccion_de_residencia'])) ?: 'N/D',
                'telefono' => 'N/D',
                'comuna' => 'N/D',
                'puesto' => $puestoEstructurado ?: ($puestoTexto ?: 'N/D'),
                'mesa' => trim((string) $this->valueByAliases($row, $idx, ['mesa'])) ?: 'N/D',
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
                $abogado->fill($payload)->save();
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
                    $payload = [
                        'nombre' => $nombre,
                        'cc' => $cc,
                        'correo' => trim((string) $this->valueByAliases($row, $idx, ['correo', 'email'])) ?: ($cc . '@sin-correo.local'),
                        'direccion' => trim((string) $this->valueByAliases($row, $idx, ['dir', 'direccion', 'direccion_de_residencia'])) ?: 'N/D',
                        'telefono' => trim((string) $this->valueByAliases($row, $idx, ['telefono', 'celular', 'telefono_celular'])) ?: 'N/D',
                        'comuna' => trim((string) ($puesto->comuna ?? 'N/D')) ?: 'N/D',
                        'puesto' => trim(($puesto->municipio ?? '') . ' - ' . ($puesto->puesto ?? $puestoTexto), ' -'),
                        'mesa' => 'Rem',
                        'observacion' => trim((string) $this->valueByAliases($row, $idx, ['observacion', 'obs'])) ?: null,
                        'activo' => true,
                    ];

                    if ($abogado) {
                        $abogado->fill($payload)->save();
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
