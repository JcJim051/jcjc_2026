<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abogado;
use App\Models\AbogadoCoordinacion;
use App\Models\Eleccion;
use App\Models\Puestos;
use App\Models\Seller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AbogadosController extends Controller
{
    public function index()
    {
        $abogados = Abogado::orderByDesc('activo')->orderBy('nombre')->get();
        return view('admin.abogados.index', compact('abogados'));
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
            $puestosMunicipio = Puestos::query()
                ->where('mun', $municipioVota)
                ->orderBy('codpuesto')
                ->orderBy('nombre')
                ->get()
                ->map(function ($p) use ($puestoVota) {
                    $totalRem = Seller::where('codcor', $p->codpuesto)->where('mesa', 'Rem')->count();
                    $ocupadosRem = Seller::where('codcor', $p->codpuesto)
                        ->where('mesa', 'Rem')
                        ->where(function ($q) {
                            $q->whereNotNull('cedula')->where('cedula', '<>', '')
                              ->orWhere(function ($q2) {
                                  $q2->whereNotNull('nombre')->where('nombre', '<>', '');
                              });
                        })
                        ->count();

                    return (object) [
                        'codpuesto' => $p->codpuesto,
                        'label' => trim(($p->mun ?? '') . ' - ' . ($p->nombre ?? ''), ' -'),
                        'total_rem' => $totalRem,
                        'ocupados_rem' => $ocupadosRem,
                        'disponibles_rem' => max(0, $totalRem - $ocupadosRem),
                        'es_puesto_vota' => mb_strtoupper(trim((string) $p->nombre)) === mb_strtoupper(trim((string) $puestoVota)),
                    ];
                })
                ->values();
        }

        $elecciones = Eleccion::query()->orderByDesc('id')->get(['id', 'nombre', 'tipo', 'estado']);
        $eleccionActiva = Eleccion::where('estado', 'activa')->max('id');
        $historialCoordinaciones = AbogadoCoordinacion::query()
            ->from('abogado_coordinaciones as ac')
            ->leftJoin('elecciones as e', 'e.id', '=', 'ac.eleccion_id')
            ->leftJoin('puestos as p', 'p.codpuesto', '=', 'ac.codpuesto')
            ->where('ac.abogado_id', $abogado->id)
            ->orderByDesc('ac.assigned_at')
            ->select(
                'ac.*',
                'e.nombre as eleccion_nombre',
                'e.tipo as eleccion_tipo',
                'p.mun as puesto_municipio',
                'p.nombre as puesto_nombre'
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
            'codpuesto' => 'required|string|exists:puestos,codpuesto',
            'eleccion_id' => 'required|integer|exists:elecciones,id',
        ]);

        [$municipioVota] = $this->splitPuestoTexto($abogado->puesto);
        $puesto = Puestos::where('codpuesto', $data['codpuesto'])->firstOrFail();

        if ($municipioVota && mb_strtoupper(trim((string) $puesto->mun)) !== mb_strtoupper(trim((string) $municipioVota))) {
            return back()->withErrors(['codpuesto' => 'Solo puedes asignar en el municipio donde vota.']);
        }

        try {
            DB::transaction(function () use ($abogado, $puesto, $data) {
                $yaAsignado = AbogadoCoordinacion::where('abogado_id', $abogado->id)
                    ->where('eleccion_id', $data['eleccion_id'])
                    ->whereNull('released_at')
                    ->exists();
                if ($yaAsignado) {
                    throw new \RuntimeException('Esta persona ya tiene una coordinación activa en esta elección.');
                }

                $slot = Seller::where('codcor', $puesto->codpuesto)
                    ->where('mesa', 'Rem')
                    ->where(function ($q) {
                        $q->whereNull('cedula')->orWhere('cedula', '');
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$slot) {
                    throw new \RuntimeException('No hay cupos remanentes disponibles en este puesto.');
                }

                $slot->update([
                    'cedula' => $abogado->cc,
                    'nombre' => $abogado->nombre,
                    'email' => $abogado->correo,
                    'telefono' => $abogado->telefono,
                    'statusasistencia' => 0,
                    'status' => 0,
                    'observacion' => trim(($slot->observacion ? $slot->observacion.' | ' : '').'Asignado desde caracterización abogados'),
                ]);

                AbogadoCoordinacion::create([
                    'abogado_id' => $abogado->id,
                    'eleccion_id' => $data['eleccion_id'],
                    'codpuesto' => $puesto->codpuesto,
                    'seller_id' => $slot->id,
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
