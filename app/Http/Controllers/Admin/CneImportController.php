<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use App\Models\Persona;
use App\Models\Referido;
use App\Models\ReferidoImportBatch;
use App\Models\ReferidoImportRow;
use App\Models\TerritorioToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CneImportController extends Controller
{
    public function index()
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        $tokens = TerritorioToken::query()
            ->where('es_consulta', false)
            ->orderByDesc('id')
            ->get(['id', 'eleccion_id', 'responsable', 'dd', 'mm', 'comuna', 'es_consulta']);
        $batches = ReferidoImportBatch::query()
            ->withCount(['rows as imported_rows' => fn ($q) => $q->where('status', 'importado')])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('admin.cne_import.index', compact('elecciones', 'tokens', 'batches'));
    }

    public function importarPostulados(Request $request)
    {
        return $this->importar($request, 'validado', 'postulado');
    }

    public function importarAcreditados(Request $request)
    {
        return $this->importar($request, 'postulado', 'acreditado');
    }

    public function previewReferidosMasivos(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'territorio_token_id' => 'required|integer|exists:territorio_tokens,id',
            'archivo' => 'required|file|mimes:xlsx,csv,txt',
        ]);

        $token = TerritorioToken::where('id', (int) $data['territorio_token_id'])
            ->where('eleccion_id', (int) $data['eleccion_id'])
            ->first();

        if (!$token) {
            return back()->with('error', 'El token seleccionado no pertenece a la eleccion indicada.')->withInput();
        }
        if ($token->es_consulta) {
            return back()->with('error', 'Este token es solo de consulta y no puede crear referidos.')->withInput();
        }

        if (!class_exists(IOFactory::class)) {
            return back()->with('error', 'PhpSpreadsheet no esta instalado.');
        }

        $rows = $this->readRowsFromUploadedFile($request->file('archivo'));
        $headerInfo = $this->findImportHeader($rows);

        if (!$headerInfo) {
            return back()->with('error', 'El archivo debe incluir las columnas obligatorias: cedula, nombre, puesto y mesa.');
        }

        [$headerRowNumber, $columnMap] = $headerInfo;

        $batch = ReferidoImportBatch::create([
            'eleccion_id' => (int) $data['eleccion_id'],
            'territorio_token_id' => (int) $data['territorio_token_id'],
            'filename' => $request->file('archivo')->getClientOriginalName(),
            'status' => 'preview',
            'created_by' => Auth::id(),
        ]);

        $total = 0;
        $valid = 0;
        $errors = 0;
        $seenCedulas = [];
        $seenMesas = [];

        foreach ($rows as $rowNumber => $row) {
            if ((int) $rowNumber <= (int) $headerRowNumber || $this->rowIsBlank($row)) {
                continue;
            }

            $payload = $this->extractImportPayload($row, $columnMap);
            $validation = $this->validateReferidoImportRow($payload, (int) $data['eleccion_id'], null, $token);

            if (($validation['error_code'] ?? null) === 'mesa_invalida' && !empty($validation['eleccion_puesto_id'])) {
                $assignedMesa = $this->findAvailableMesaForImport(
                    (int) $data['eleccion_id'],
                    (int) $validation['eleccion_puesto_id'],
                    (int) ($payload['mesa_num'] ?? 0),
                    $seenMesas,
                    'last'
                );

                if ($assignedMesa) {
                    $payload['observacion'] = $this->appendImportObservation(
                        $payload['observacion'],
                        'Mesa original ' . (string) ($payload['mesa_num'] ?? 'N/D') . ' no existe. Reasignada automaticamente a mesa ' . $assignedMesa . '.'
                    );
                    $payload['mesa_num'] = $assignedMesa;
                    $validation = $this->validateReferidoImportRow($payload, (int) $data['eleccion_id'], (int) $validation['eleccion_puesto_id'], $token);
                }
            }

            $cedulaKey = (string) ($payload['cedula'] ?? '');
            if ($validation['status'] === 'valid' && $cedulaKey !== '') {
                if (isset($seenCedulas[$cedulaKey])) {
                    $validation = $this->rowError('duplicado', 'La cedula esta duplicada dentro del archivo.');
                } else {
                    $seenCedulas[$cedulaKey] = true;
                }
            }

            if ($validation['status'] === 'valid') {
                $mesaKey = $validation['eleccion_puesto_id'] . '-' . (int) $payload['mesa_num'];
                if (isset($seenMesas[$mesaKey])) {
                    $assignedMesa = $this->findAvailableMesaForImport(
                        (int) $data['eleccion_id'],
                        (int) $validation['eleccion_puesto_id'],
                        (int) $payload['mesa_num'],
                        $seenMesas,
                        'next'
                    );

                    if ($assignedMesa) {
                        $payload['observacion'] = $this->appendImportObservation(
                            $payload['observacion'],
                            'Mesa original ' . (int) $payload['mesa_num'] . ' repetida en lote. Reasignada automaticamente a mesa ' . $assignedMesa . '.'
                        );
                        $payload['mesa_num'] = $assignedMesa;
                        $validation = $this->validateReferidoImportRow($payload, (int) $data['eleccion_id'], (int) $validation['eleccion_puesto_id'], $token);
                        $mesaKey = $validation['eleccion_puesto_id'] . '-' . (int) $payload['mesa_num'];
                    } else {
                        $validation = $this->rowError('mesa_ocupada_lote', 'La misma mesa aparece mas de una vez en este lote y no hay otra mesa disponible en el puesto.');
                    }
                }

                if ($validation['status'] === 'valid' && isset($seenMesas[$mesaKey])) {
                    $validation = $this->rowError('mesa_ocupada_lote', 'La misma mesa aparece mas de una vez en este lote y no hay otra mesa disponible en el puesto.');
                }

                if ($validation['status'] === 'valid') {
                    $seenMesas[$mesaKey] = true;
                }
            }

            ReferidoImportRow::create([
                'batch_id' => $batch->id,
                'row_number' => (int) $rowNumber,
                'cedula' => $payload['cedula'],
                'nombre' => $payload['nombre'],
                'email' => $payload['email'],
                'telefono' => $payload['telefono'],
                'puesto_texto' => $payload['puesto_texto'],
                'municipio_texto' => $payload['municipio_texto'],
                'mesa_num' => $payload['mesa_num'],
                'observacion' => $payload['observacion'],
                'eleccion_puesto_id' => $validation['eleccion_puesto_id'],
                'status' => $validation['status'],
                'error_code' => $validation['error_code'],
                'error_message' => $validation['error_message'],
            ]);

            $total++;
            if ($validation['status'] === 'valid') {
                $valid++;
            } else {
                $errors++;
            }
        }

        $batch->update([
            'total_rows' => $total,
            'valid_rows' => $valid,
            'error_rows' => $errors,
        ]);

        if ($total === 0) {
            $batch->update(['status' => 'cancelado']);
            return redirect()->route('admin.cne_import.index')
                ->with('error', 'No se encontraron filas de datos para previsualizar.');
        }

        return redirect()->route('admin.cne_import.referidos_masivos.show', $batch)
            ->with('success', 'Lote previsualizado. Revisa los errores antes de aplicar.');
    }

    public function showReferidosPreview(ReferidoImportBatch $batch)
    {
        $batch->load(['rows' => fn ($q) => $q->orderBy('row_number')]);
        $eleccion = Eleccion::find($batch->eleccion_id);
        $token = TerritorioToken::find($batch->territorio_token_id);
        $counters = $batch->rows
            ->groupBy(fn ($row) => $row->status === 'valid' ? 'validos' : ($row->status === 'importado' ? 'importados' : ($row->error_code ?: 'errores')))
            ->map->count();

        return view('admin.cne_import.preview', compact('batch', 'eleccion', 'token', 'counters'));
    }

    public function applyReferidosMasivos(ReferidoImportBatch $batch)
    {
        if ($batch->status !== 'preview') {
            return redirect()->route('admin.cne_import.referidos_masivos.show', $batch)
                ->with('error', 'Este lote ya no esta en estado de previsualizacion.');
        }

        $summary = [
            'personas_creadas' => 0,
            'personas_actualizadas' => 0,
            'referidos_creados' => 0,
            'filas_omitidas' => 0,
        ];
        $token = TerritorioToken::where('id', (int) $batch->territorio_token_id)
            ->where('eleccion_id', (int) $batch->eleccion_id)
            ->first();

        if (!$token) {
            return redirect()->route('admin.cne_import.referidos_masivos.show', $batch)
                ->with('error', 'El token del lote ya no existe o no pertenece a la eleccion.');
        }
        if ($token->es_consulta) {
            return redirect()->route('admin.cne_import.referidos_masivos.show', $batch)
                ->with('error', 'Este token es solo de consulta y no puede crear referidos.');
        }

        DB::beginTransaction();
        try {
            $rows = ReferidoImportRow::query()
                ->where('batch_id', $batch->id)
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            $seenMesas = [];
            foreach ($rows as $row) {
                if ($row->status !== 'valid') {
                    $summary['filas_omitidas']++;
                    continue;
                }

                $payload = [
                    'cedula' => $row->cedula,
                    'nombre' => $row->nombre,
                    'email' => $row->email,
                    'telefono' => $row->telefono,
                    'puesto_texto' => $row->puesto_texto,
                    'municipio_texto' => $row->municipio_texto,
                    'mesa_num' => $row->mesa_num,
                    'observacion' => $row->observacion,
                ];

                $validation = $this->validateReferidoImportRow($payload, (int) $batch->eleccion_id, (int) $row->eleccion_puesto_id, $token);
                if ($validation['status'] !== 'valid') {
                    $row->update($validation);
                    $summary['filas_omitidas']++;
                    continue;
                }

                $mesaKey = $validation['eleccion_puesto_id'] . '-' . (int) $row->mesa_num;
                if (isset($seenMesas[$mesaKey])) {
                    $assignedMesa = $this->findAvailableMesaForImport(
                        (int) $batch->eleccion_id,
                        (int) $validation['eleccion_puesto_id'],
                        (int) $row->mesa_num,
                        $seenMesas,
                        'next'
                    );

                    if ($assignedMesa) {
                        $row->mesa_num = $assignedMesa;
                        $row->observacion = $this->appendImportObservation(
                            $row->observacion,
                            'Mesa reasignada automaticamente durante aplicacion a mesa ' . $assignedMesa . '.'
                        );
                        $row->save();
                        $payload['mesa_num'] = $assignedMesa;
                        $validation = $this->validateReferidoImportRow($payload, (int) $batch->eleccion_id, (int) $row->eleccion_puesto_id, $token);
                        $mesaKey = $validation['eleccion_puesto_id'] . '-' . (int) $row->mesa_num;
                    } else {
                        $row->update($this->rowError('mesa_ocupada_lote', 'La mesa se ocupo por otra fila del mismo lote durante la aplicacion y no hay otra disponible.'));
                        $summary['filas_omitidas']++;
                        continue;
                    }
                }
                $seenMesas[$mesaKey] = true;

                $persona = Persona::where('cedula', $row->cedula)->first();
                if ($persona) {
                    $updates = [
                        'nombre' => $row->nombre,
                        'nombre_original' => $row->nombre,
                        'nombre_normalizado' => $this->normalizeName($row->nombre),
                    ];
                    if (!empty($row->email)) {
                        $updates['email'] = mb_strtolower(trim((string) $row->email), 'UTF-8');
                    }
                    if (!empty($row->telefono)) {
                        $updates['telefono'] = trim((string) $row->telefono);
                    }
                    $persona->update($updates);
                    $summary['personas_actualizadas']++;
                } else {
                    $persona = Persona::create([
                        'cedula' => $row->cedula,
                        'nombre' => $row->nombre,
                        'nombre_original' => $row->nombre,
                        'nombre_normalizado' => $this->normalizeName($row->nombre),
                        'email' => $row->email ? mb_strtolower(trim((string) $row->email), 'UTF-8') : null,
                        'telefono' => $row->telefono,
                    ]);
                    $summary['personas_creadas']++;
                }

                $observaciones = trim((string) ($row->observacion ?? ''));
                $audit = '[' . now()->format('Y-m-d H:i:s') . '] sistema: Creado por carga masiva CNE lote #' . $batch->id . '.';
                $observaciones = $observaciones !== '' ? ($observaciones . PHP_EOL . $audit) : $audit;

                $referido = Referido::create([
                    'persona_id' => $persona->id,
                    'eleccion_id' => $batch->eleccion_id,
                    'eleccion_puesto_id' => $row->eleccion_puesto_id,
                    'territorio_token_id' => $batch->territorio_token_id,
                    'mesa_num' => (int) $row->mesa_num,
                    'cedula_pdf_path' => null,
                    'estado' => 'referido',
                    'observaciones' => $observaciones,
                ]);

                $row->update([
                    'status' => 'importado',
                    'error_code' => null,
                    'error_message' => null,
                    'referido_id' => $referido->id,
                ]);
                $summary['referidos_creados']++;
            }

            $batch->update([
                'status' => 'aplicado',
                'applied_at' => now(),
                'valid_rows' => ReferidoImportRow::where('batch_id', $batch->id)->whereIn('status', ['valid', 'importado'])->count(),
                'error_rows' => ReferidoImportRow::where('batch_id', $batch->id)->where('status', 'error')->count(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('admin.cne_import.referidos_masivos.show', $batch)
                ->with('error', 'No se pudo aplicar el lote: ' . $e->getMessage());
        }

        return redirect()->route('admin.cne_import.referidos_masivos.show', $batch)
            ->with('success', 'Lote aplicado. Personas creadas: ' . $summary['personas_creadas'] .
                '. Personas actualizadas: ' . $summary['personas_actualizadas'] .
                '. Referidos creados: ' . $summary['referidos_creados'] .
                '. Filas omitidas: ' . $summary['filas_omitidas'] . '.');
    }

    public function cancelReferidosMasivos(ReferidoImportBatch $batch)
    {
        if ($batch->status === 'aplicado') {
            return redirect()->route('admin.cne_import.referidos_masivos.show', $batch)
                ->with('error', 'No se puede cancelar un lote ya aplicado.');
        }

        $batch->update(['status' => 'cancelado']);

        return redirect()->route('admin.cne_import.index')
            ->with('success', 'Lote cancelado correctamente.');
    }

    public function downloadReferidosErrores(ReferidoImportBatch $batch)
    {
        $rows = ReferidoImportRow::where('batch_id', $batch->id)
            ->where('status', 'error')
            ->orderBy('row_number')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Errores_Importacion');
        $sheet->fromArray([
            ['Fila', 'Cedula', 'Nombre', 'Telefono', 'Municipio', 'Puesto', 'Mesa', 'Codigo error', 'Mensaje error'],
        ], null, 'A1');

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->fromArray([[
                (int) $r->row_number,
                (string) ($r->cedula ?? ''),
                (string) ($r->nombre ?? ''),
                (string) ($r->telefono ?? ''),
                (string) ($r->municipio_texto ?? ''),
                (string) ($r->puesto_texto ?? ''),
                (string) ($r->mesa_num ?? ''),
                (string) ($r->error_code ?? ''),
                (string) ($r->error_message ?? ''),
            ]], null, 'A' . $rowNum);
            $rowNum++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'errores_importacion_referidos_lote_' . $batch->id . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportarAsignadosPendientes(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
        ]);

        $rows = DB::table('referidos')
            ->join('personas', 'personas.id', '=', 'referidos.persona_id')
            ->where('referidos.eleccion_id', $data['eleccion_id'])
            ->whereIn('referidos.estado', ['asignado', 'validado'])
            ->orderBy('personas.cedula')
            ->get([
                'personas.cedula',
                'personas.nombre',
                'personas.email',
                'personas.telefono',
                'referidos.estado',
            ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pendientes_Postulacion');
        $sheet->fromArray([
            ['Identificacion', 'Nombre', 'Correo', 'Celular', 'Estado actual'],
        ], null, 'A1');

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->fromArray([[
                (string) ($r->cedula ?? ''),
                (string) ($r->nombre ?? ''),
                (string) ($r->email ?? ''),
                (string) ($r->telefono ?? ''),
                (string) ($r->estado ?? ''),
            ]], null, 'A' . $rowNum);
            $rowNum++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'asignados_pendientes_postulacion_eleccion_' . $data['eleccion_id'] . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function importar(Request $request, string $estadoActual, string $estadoNuevo)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'archivo' => 'required|file|mimes:xlsx,csv,txt',
        ]);

        if (!class_exists(IOFactory::class)) {
            return back()->with('error', 'PhpSpreadsheet no esta instalado.');
        }

        $filePath = $request->file('archivo')->getRealPath();

        $ext = strtolower((string) $request->file('archivo')->getClientOriginalExtension());
        if (in_array($ext, ['csv', 'txt'], true)) {
            $reader = IOFactory::createReader('Csv');
            $reader->setDelimiter(';');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
        } else {
            $reader = IOFactory::createReader('Xlsx');
        }
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $header = $rows[1] ?? [];

        $colMap = $this->mapColumns($header);
        if (!isset($colMap['identificacion'])) {
            return back()->with('error', 'No se encontro la columna Identificacion/Cedula.');
        }

        $updated = 0;

        foreach ($rows as $idx => $row) {
            if ($idx === 1) {
                continue;
            }

            $cedula = trim((string) ($row[$colMap['identificacion']] ?? ''));
            if ($cedula === '') {
                continue;
            }
            $cedula = preg_replace('/\D+/', '', $cedula) ?: $cedula;

            $affected = DB::table('referidos')
                ->join('personas', 'personas.id', '=', 'referidos.persona_id')
                ->where('referidos.eleccion_id', $data['eleccion_id'])
                ->where('referidos.estado', $estadoActual)
                ->where('personas.cedula', $cedula)
                ->update([
                    'referidos.estado' => $estadoNuevo,
                    'referidos.updated_at' => now(),
                ]);

            $updated += $affected;
        }

        return redirect()->route('admin.cne_import.index')
            ->with('success', 'Registros actualizados a ' . $estadoNuevo . ': ' . $updated);
    }

    private function readRowsFromUploadedFile($file): array
    {
        $filePath = $file->getRealPath();
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($ext, ['csv', 'txt'], true)) {
            $reader = IOFactory::createReader('Csv');
            $reader->setDelimiter($this->detectDelimiter($filePath));
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
        } else {
            $reader = IOFactory::createReader('Xlsx');
        }

        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($filePath);
        return $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
    }

    private function detectDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        $line = $handle ? (string) fgets($handle) : '';
        if ($handle) {
            fclose($handle);
        }

        $semicolon = substr_count($line, ';');
        $comma = substr_count($line, ',');
        $tab = substr_count($line, "\t");

        if ($tab >= $semicolon && $tab >= $comma && $tab > 0) {
            return "\t";
        }
        return $semicolon >= $comma ? ';' : ',';
    }

    private function findImportHeader(array $rows): ?array
    {
        foreach ($rows as $rowNumber => $row) {
            if ((int) $rowNumber > 20) {
                break;
            }

            $map = [];
            foreach ($row as $col => $name) {
                $key = $this->norm($name);
                if (in_array($key, ['cedula', 'cc', 'identificacion', 'documento', 'documento de identificacion'], true)) {
                    $map['cedula'] = $col;
                } elseif (in_array($key, ['nombre', 'nombres', 'nombre completo', 'testigo'], true)) {
                    $map['nombre'] = $col;
                } elseif (in_array($key, ['puesto', 'puesto de votacion', 'puesto votacion', 'nombre puesto'], true)) {
                    $map['puesto'] = $col;
                } elseif (in_array($key, ['municipio', 'ciudad', 'mpio', 'nombre municipio'], true)) {
                    $map['municipio'] = $col;
                } elseif (in_array($key, ['mesa', 'ubicacion en mesa', 'ubicacion mesa'], true)) {
                    $map['mesa'] = $col;
                } elseif (in_array($key, ['correo', 'email', 'correo electronico'], true)) {
                    $map['email'] = $col;
                } elseif (in_array($key, ['telefono', 'celular', 'telefono celular', 'movil', 'whatsapp', 'numero celular', 'numero de celular'], true)) {
                    $map['telefono'] = $col;
                } elseif (in_array($key, ['observacion', 'observaciones', 'obs'], true)) {
                    $map['observacion'] = $col;
                }
            }

            foreach (['cedula', 'nombre', 'puesto', 'mesa'] as $required) {
                if (!isset($map[$required])) {
                    continue 2;
                }
            }

            return [(int) $rowNumber, $map];
        }

        return null;
    }

    private function extractImportPayload(array $row, array $map): array
    {
        return [
            'cedula' => $this->cleanCedula($this->cellValue($row, $map, 'cedula')),
            'nombre' => trim((string) $this->cellValue($row, $map, 'nombre')),
            'email' => mb_strtolower(trim((string) $this->cellValue($row, $map, 'email')), 'UTF-8'),
            'telefono' => trim((string) $this->cellValue($row, $map, 'telefono')),
            'puesto_texto' => trim((string) $this->cellValue($row, $map, 'puesto')),
            'municipio_texto' => trim((string) $this->cellValue($row, $map, 'municipio')),
            'mesa_num' => $this->parseMesa($this->cellValue($row, $map, 'mesa')),
            'observacion' => trim((string) $this->cellValue($row, $map, 'observacion')),
        ];
    }

    private function cellValue(array $row, array $map, string $key): string
    {
        if (!isset($map[$key])) {
            return '';
        }

        return trim((string) ($row[$map[$key]] ?? ''));
    }

    private function validateReferidoImportRow(array $payload, int $eleccionId, ?int $forcedPuestoId = null, ?TerritorioToken $token = null): array
    {
        if (($payload['cedula'] ?? '') === '' || ($payload['nombre'] ?? '') === '' || ($payload['puesto_texto'] ?? '') === '' || empty($payload['mesa_num'])) {
            return $this->rowError('incompleto', 'Faltan datos obligatorios: cedula, nombre, puesto o mesa.');
        }

        if (!$token) {
            return $this->rowError('token_invalido', 'No se pudo validar el alcance territorial del token.');
        }
        if ($token->es_consulta) {
            return $this->rowError('token_consulta', 'Este token es solo de consulta y no puede crear referidos.');
        }

        $cedulaExists = DB::table('referidos as r')
            ->join('personas as p', 'p.id', '=', 'r.persona_id')
            ->where('r.eleccion_id', $eleccionId)
            ->where('p.cedula', $payload['cedula'])
            ->exists();
        if ($cedulaExists) {
            return $this->rowError('duplicado', 'La cedula ya existe en referidos para esta eleccion.');
        }

        if ($forcedPuestoId) {
            $puesto = DB::table('eleccion_puestos')
                ->where('id', $forcedPuestoId)
                ->where('eleccion_id', $eleccionId)
                ->first();
            if (!$puesto) {
                return $this->rowError('puesto_no_encontrado', 'El puesto guardado en el lote ya no existe en esta eleccion.');
            }
            if (!$this->puestoBelongsToTokenScope($puesto, $token)) {
                return $this->rowError('puesto_fuera_token', 'El puesto no pertenece al municipio/comuna del token seleccionado.');
            }
            if (!$this->municipioMatchesTokenScope((string) ($payload['municipio_texto'] ?? ''), $token)) {
                return $this->rowError('municipio_fuera_token', 'El municipio del archivo no coincide con el municipio del token.');
            }
        } else {
            $puestoResult = $this->findImportPuesto($eleccionId, (string) $payload['puesto_texto'], (string) ($payload['municipio_texto'] ?? ''), $token);
            if ($puestoResult['error']) {
                return $this->rowError($puestoResult['error'], $puestoResult['message']);
            }
            $puesto = $puestoResult['puesto'];
        }

        $mesaNum = (int) $payload['mesa_num'];
        $eleccionMesa = DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puesto->id)
            ->where('mesa_num', $mesaNum)
            ->first();

        if (!$eleccionMesa) {
            return $this->rowError('mesa_invalida', 'La mesa no existe en eleccion_mesas para ese puesto.', (int) $puesto->id);
        }

        $mesaBloqueada = DB::table('mesa_bloqueos')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puesto->id)
            ->where('mesa_num', $mesaNum)
            ->exists();
        if ($mesaBloqueada) {
            return $this->rowError('mesa_bloqueada', 'La mesa esta bloqueada.');
        }

        $mesaConReferido = Referido::query()
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puesto->id)
            ->where('mesa_num', $mesaNum)
            ->where('estado', '<>', 'rechazado')
            ->exists();
        if ($mesaConReferido) {
            return $this->rowError('mesa_ocupada', 'La mesa ya tiene un referido activo.');
        }

        $mesaConAsignacion = DB::table('asignaciones')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_mesa_id', $eleccionMesa->id)
            ->where('estado', 'activo')
            ->where('rol', 'testigo_mesa')
            ->exists();
        if ($mesaConAsignacion) {
            return $this->rowError('mesa_ocupada', 'La mesa ya tiene una asignacion activa.');
        }

        return [
            'status' => 'valid',
            'error_code' => null,
            'error_message' => null,
            'eleccion_puesto_id' => (int) $puesto->id,
        ];
    }

    private function rowError(string $code, string $message, ?int $eleccionPuestoId = null): array
    {
        return [
            'status' => 'error',
            'error_code' => $code,
            'error_message' => $message,
            'eleccion_puesto_id' => $eleccionPuestoId,
        ];
    }

    private function findAvailableMesaForImport(
        int $eleccionId,
        int $puestoId,
        int $preferredMesa,
        array $reservedMesas,
        string $mode = 'next'
    ): ?int {
        $reserved = [];
        foreach ($reservedMesas as $mesaKey => $used) {
            if (!$used) {
                continue;
            }
            [$reservedPuestoId, $reservedMesa] = array_pad(explode('-', (string) $mesaKey, 2), 2, null);
            if ((int) $reservedPuestoId === $puestoId) {
                $reserved[] = (int) $reservedMesa;
            }
        }

        $mesasUniverso = DB::table('eleccion_mesas')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->orderBy('mesa_num')
            ->pluck('mesa_num')
            ->map(fn ($mesa) => (int) $mesa)
            ->unique()
            ->values()
            ->all();

        if (empty($mesasUniverso)) {
            return null;
        }

        $bloqueadas = DB::table('mesa_bloqueos')
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->pluck('mesa_num')
            ->map(fn ($mesa) => (int) $mesa)
            ->all();

        $ocupadasReferidos = Referido::query()
            ->where('eleccion_id', $eleccionId)
            ->where('eleccion_puesto_id', $puestoId)
            ->where('estado', '<>', 'rechazado')
            ->pluck('mesa_num')
            ->map(fn ($mesa) => (int) $mesa)
            ->all();

        $ocupadasAsignaciones = DB::table('asignaciones as a')
            ->join('eleccion_mesas as em', 'em.id', '=', 'a.eleccion_mesa_id')
            ->where('a.eleccion_id', $eleccionId)
            ->where('a.estado', 'activo')
            ->where('a.rol', 'testigo_mesa')
            ->where('em.eleccion_puesto_id', $puestoId)
            ->pluck('em.mesa_num')
            ->map(fn ($mesa) => (int) $mesa)
            ->all();

        $noDisponibles = array_unique(array_merge($reserved, $bloqueadas, $ocupadasReferidos, $ocupadasAsignaciones));
        $disponibles = array_values(array_filter($mesasUniverso, fn ($mesa) => !in_array((int) $mesa, $noDisponibles, true)));

        if (empty($disponibles)) {
            return null;
        }

        if ($mode === 'last') {
            return (int) max($disponibles);
        }

        foreach ($disponibles as $mesa) {
            if ((int) $mesa > $preferredMesa) {
                return (int) $mesa;
            }
        }

        return (int) $disponibles[0];
    }

    private function appendImportObservation(?string $current, string $message): string
    {
        $current = trim((string) $current);
        return $current === '' ? $message : ($current . PHP_EOL . $message);
    }

    private function findImportPuesto(int $eleccionId, string $puestoTexto, string $municipioTexto, TerritorioToken $token): array
    {
        $needle = $this->norm($puestoTexto);
        if (!$this->municipioMatchesTokenScope($municipioTexto, $token)) {
            return [
                'puesto' => null,
                'error' => 'municipio_fuera_token',
                'message' => 'El municipio del archivo no coincide con el municipio del token.',
            ];
        }

        $puestosQuery = DB::table('eleccion_puestos')
            ->where('eleccion_id', $eleccionId)
            ->where('dd', $token->dd)
            ->where('mm', $token->mm);

        $comunasToken = $this->parseComunasToken($token->comuna);
        if (!empty($comunasToken)) {
            $puestosQuery->whereIn('comuna', $comunasToken);
        }

        $puestos = $puestosQuery->get(['id', 'municipio', 'puesto', 'dd', 'mm', 'zz', 'pp', 'comuna']);

        $matches = [];
        foreach ($puestos as $puesto) {
            $puestoNorm = $this->norm((string) $puesto->puesto);
            $fullNorm = $this->norm(trim((string) $puesto->municipio . ' ' . (string) $puesto->puesto));

            $score = 0;
            if ($needle === $puestoNorm || $needle === $fullNorm) {
                $score = 100;
            } elseif (str_contains($fullNorm, $needle) || str_contains($needle, $puestoNorm)) {
                $score = 90;
            } else {
                similar_text($needle, $fullNorm, $percentFull);
                similar_text($needle, $puestoNorm, $percentPuesto);
                $score = (int) max($percentFull, $percentPuesto);
            }

            if ($score >= 78) {
                $matches[] = ['puesto' => $puesto, 'score' => $score];
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        if (empty($matches)) {
            return [
                'puesto' => null,
                'error' => 'puesto_no_encontrado',
                'message' => 'No se encontro el puesto dentro del municipio/comuna del token seleccionado.',
            ];
        }

        $topScore = $matches[0]['score'];
        $topMatches = array_values(array_filter($matches, fn ($m) => abs($m['score'] - $topScore) <= 2));
        if (count($topMatches) > 1) {
            return [
                'puesto' => null,
                'error' => 'puesto_ambiguo',
                'message' => 'El nombre del puesto coincide con mas de un registro dentro del alcance del token. Agrega municipio o codigo para desambiguar.',
            ];
        }

        return ['puesto' => $matches[0]['puesto'], 'error' => null, 'message' => null];
    }

    private function municipioMatchesTokenScope(string $municipioTexto, TerritorioToken $token): bool
    {
        $municipioTexto = trim($municipioTexto);
        if ($municipioTexto === '') {
            return true;
        }

        $municipioToken = DB::table('eleccion_puestos')
            ->where('eleccion_id', $token->eleccion_id)
            ->where('dd', $token->dd)
            ->where('mm', $token->mm)
            ->value('municipio');

        if (!$municipioToken) {
            return false;
        }

        $tokenNorm = $this->norm($municipioToken);
        $fileNorm = $this->norm($municipioTexto);

        return $tokenNorm === $fileNorm
            || str_contains($fileNorm, $tokenNorm)
            || str_contains($tokenNorm, $fileNorm);
    }

    private function puestoBelongsToTokenScope(object $puesto, TerritorioToken $token): bool
    {
        if ((int) $puesto->eleccion_id !== (int) $token->eleccion_id) {
            return false;
        }
        if ((string) $puesto->dd !== (string) $token->dd || (string) $puesto->mm !== (string) $token->mm) {
            return false;
        }

        $comunasToken = $this->parseComunasToken($token->comuna);
        if (!empty($comunasToken) && !in_array((string) ($puesto->comuna ?? ''), $comunasToken, true)) {
            return false;
        }

        return true;
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

    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cleanCedula($value): string
    {
        $clean = preg_replace('/\D+/', '', trim((string) $value));
        return $clean ?: trim((string) $value);
    }

    private function parseMesa($value): ?int
    {
        $clean = preg_replace('/\D+/', '', trim((string) $value));
        if ($clean === '') {
            return null;
        }

        return max(0, (int) $clean);
    }

    private function normalizeName(?string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)), 'UTF-8');
    }

    private function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $col => $name) {
            $key = $this->norm($name);
            if (in_array($key, ['identificacion', 'cedula', 'documento de identificacion', 'documento'], true)) {
                $map['identificacion'] = $col;
            }
        }

        return $map;
    }

    private function norm($value): string
    {
        $value = is_string($value) ? $value : '';
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($trans !== false) {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
