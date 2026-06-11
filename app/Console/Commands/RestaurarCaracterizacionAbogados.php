<?php

namespace App\Console\Commands;

use App\Models\Abogado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RestaurarCaracterizacionAbogados extends Command
{
    protected $signature = 'abogados:restaurar-caracterizacion
        {archivo : Ruta absoluta o relativa al archivo Excel de respaldo}
        {--dry-run : Genera la previsualización sin modificar la base de datos}
        {--apply : Aplica únicamente los cambios considerados seguros}';

    protected $description = 'Restaura campos personales de abogados por cédula sin modificar sus coordinaciones';

    private const FIELD_MAP = [
        'Correo' => 'correo',
        'Teléfono' => 'telefono',
        'Dirección' => 'direccion',
        'Observación' => 'observacion',
        'Puesto donde vota' => 'puesto',
        'Mesa' => 'mesa',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run');

        if ($apply === $dryRun) {
            $this->error('Debes usar exactamente una opción: --dry-run o --apply.');
            return self::FAILURE;
        }

        $archivo = $this->resolveFilePath((string) $this->argument('archivo'));
        if (!$archivo || !is_file($archivo)) {
            $this->error('No se encontró el archivo indicado.');
            return self::FAILURE;
        }

        try {
            $sourceRows = $this->readSourceRows($archivo);
        } catch (\Throwable $e) {
            $this->error('No se pudo leer el Excel: ' . $e->getMessage());
            return self::FAILURE;
        }

        $coordBefore = $this->coordinationFingerprint();
        $analysis = $this->analyse($sourceRows);
        $reportPath = $this->writeReport($analysis['report'], $apply ? 'apply' : 'dry-run');

        $this->renderSummary($analysis, $reportPath);

        if ($dryRun) {
            $this->info('Simulación terminada. No se modificó la base de datos.');
            return self::SUCCESS;
        }

        if (empty($analysis['updates'])) {
            $this->warn('No hay cambios seguros para aplicar.');
            return self::SUCCESS;
        }

        if (!$this->confirm('¿Aplicar estos cambios personales? Las coordinaciones no serán modificadas.')) {
            $this->warn('Operación cancelada.');
            return self::SUCCESS;
        }

        $applied = 0;
        $skippedConcurrent = 0;

        DB::transaction(function () use ($analysis, &$applied, &$skippedConcurrent) {
            foreach ($analysis['updates'] as $abogadoId => $item) {
                $abogado = Abogado::whereKey($abogadoId)->lockForUpdate()->first();
                if (!$abogado) {
                    $skippedConcurrent++;
                    continue;
                }

                $payload = [];
                foreach ($item['fields'] as $field => $change) {
                    if ($this->comparable($abogado->{$field}) !== $this->comparable($change['current'])) {
                        $skippedConcurrent++;
                        continue 2;
                    }
                    $payload[$field] = $change['source'];
                }

                if (!empty($payload)) {
                    $abogado->fill($payload)->save();
                    $applied++;
                }
            }
        });

        $coordAfter = $this->coordinationFingerprint();
        if ($coordBefore !== $coordAfter) {
            $this->error('ALERTA: cambió la huella de coordinaciones. Revisa inmediatamente el respaldo.');
            return self::FAILURE;
        }

        $this->info("Abogados actualizados: {$applied}");
        if ($skippedConcurrent > 0) {
            $this->warn("Omitidos por cambios concurrentes: {$skippedConcurrent}");
        }
        $this->info('Coordinaciones verificadas: sin cambios.');

        return self::SUCCESS;
    }

    private function readSourceRows(string $archivo): array
    {
        $reader = IOFactory::createReaderForFile($archivo);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($archivo);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            throw new \RuntimeException('El archivo no contiene registros.');
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_shift($rows));
        $indexes = array_flip($headers);

        foreach (array_merge(['Cédula', 'Nombre'], array_keys(self::FIELD_MAP)) as $required) {
            if (!array_key_exists($required, $indexes)) {
                throw new \RuntimeException("Falta la columna obligatoria: {$required}.");
            }
        }

        $result = [];
        foreach ($rows as $offset => $row) {
            $cedula = $this->digitsOnly($row[$indexes['Cédula']] ?? '');
            if ($cedula === '') {
                continue;
            }

            $fields = [];
            foreach (self::FIELD_MAP as $excelColumn => $databaseField) {
                $fields[$databaseField] = trim((string) ($row[$indexes[$excelColumn]] ?? ''));
            }

            $result[] = [
                'row' => $offset + 2,
                'cedula' => $cedula,
                'nombre' => trim((string) ($row[$indexes['Nombre']] ?? '')),
                'fields' => $fields,
            ];
        }

        return $result;
    }

    private function analyse(array $sourceRows): array
    {
        $updates = [];
        $report = [];
        $counts = [
            'source_rows' => count($sourceRows),
            'matched' => 0,
            'not_found' => 0,
            'duplicate_source' => 0,
            'duplicate_database' => 0,
            'safe_fields' => 0,
            'conflicts' => 0,
            'unchanged' => 0,
            'invalid_source' => 0,
        ];

        $sourceCounts = collect($sourceRows)->countBy('cedula');
        $abogados = Abogado::whereIn('cc', array_column($sourceRows, 'cedula'))
            ->get()
            ->groupBy(fn ($abogado) => $this->digitsOnly($abogado->cc));
        $coordinationLabels = $this->coordinationLabelsByAbogado();

        foreach ($sourceRows as $sourceRow) {
            if (($sourceCounts[$sourceRow['cedula']] ?? 0) > 1) {
                $counts['duplicate_source']++;
                $report[] = $this->reportRow($sourceRow, null, null, null, 'duplicado_respaldo', 'La cédula aparece más de una vez en el Excel y requiere revisión manual');
                continue;
            }

            $matches = $abogados->get($sourceRow['cedula'], collect());
            if ($matches->isEmpty()) {
                $counts['not_found']++;
                $report[] = $this->reportRow($sourceRow, null, null, null, 'no_encontrado', 'Cédula no encontrada en producción');
                continue;
            }
            if ($matches->count() > 1) {
                $counts['duplicate_database']++;
                $report[] = $this->reportRow($sourceRow, null, null, null, 'duplicado_base', 'La cédula corresponde a más de una caracterización y no se modificará');
                continue;
            }

            $abogado = $matches->first();
            $counts['matched']++;
            foreach ($sourceRow['fields'] as $field => $sourceValue) {
                $currentValue = trim((string) ($abogado->{$field} ?? ''));

                if (!$this->isUsefulSourceValue($field, $sourceValue)) {
                    $counts['invalid_source']++;
                    $report[] = $this->reportRow($sourceRow, $field, $currentValue, $sourceValue, 'omitido', 'El respaldo no contiene un valor útil');
                    continue;
                }

                if ($this->comparable($currentValue) === $this->comparable($sourceValue)) {
                    $counts['unchanged']++;
                    continue;
                }

                if ($this->isSafeToRestore($field, $currentValue, $coordinationLabels[$abogado->id] ?? [])) {
                    $counts['safe_fields']++;
                    $updates[$abogado->id]['cedula'] = $sourceRow['cedula'];
                    $updates[$abogado->id]['nombre'] = $sourceRow['nombre'];
                    $updates[$abogado->id]['fields'][$field] = [
                        'current' => $currentValue,
                        'source' => $sourceValue,
                    ];
                    $report[] = $this->reportRow($sourceRow, $field, $currentValue, $sourceValue, 'restaurar', 'Valor compatible con la importación dañina');
                } else {
                    $counts['conflicts']++;
                    $report[] = $this->reportRow($sourceRow, $field, $currentValue, $sourceValue, 'conflicto', 'El valor actual parece haber sido editado y no se reemplazará');
                }
            }
        }

        return compact('updates', 'report', 'counts');
    }

    private function isSafeToRestore(string $field, string $currentValue, array $coordinationLabels): bool
    {
        $current = $this->comparable($currentValue);
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
                if ($current === $this->comparable($label)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isUsefulSourceValue(string $field, string $value): bool
    {
        $normalized = $this->comparable($value);
        if ($normalized === '' || $normalized === 'n/d') {
            return false;
        }

        if ($field === 'correo' && substr($normalized, -17) === '@sin-correo.local') {
            return false;
        }

        return true;
    }

    private function coordinationLabelsByAbogado(): array
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

    private function coordinationFingerprint(): string
    {
        $rows = DB::table('abogado_coordinaciones')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => json_encode((array) $row, JSON_UNESCAPED_UNICODE))
            ->implode("\n");

        return hash('sha256', $rows);
    }

    private function writeReport(array $rows, string $mode): string
    {
        $directory = 'restauraciones';
        $filename = $directory . '/restauracion_abogados_' . $mode . '_' . now()->format('Ymd_His') . '.csv';
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['fila_excel', 'cedula', 'nombre', 'campo', 'valor_actual', 'valor_respaldo', 'estado', 'detalle'], ';');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ';');
        }
        rewind($stream);
        Storage::disk('local')->put($filename, stream_get_contents($stream));
        fclose($stream);

        return storage_path('app/' . $filename);
    }

    private function reportRow(array $sourceRow, ?string $field, ?string $current, ?string $source, string $status, string $detail): array
    {
        return [
            $sourceRow['row'],
            $sourceRow['cedula'],
            $sourceRow['nombre'],
            $field ?? '-',
            $current ?? '-',
            $source ?? '-',
            $status,
            $detail,
        ];
    }

    private function renderSummary(array $analysis, string $reportPath): void
    {
        $counts = $analysis['counts'];
        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Filas del respaldo', $counts['source_rows']],
                ['Abogados encontrados', $counts['matched']],
                ['Cédulas no encontradas', $counts['not_found']],
                ['Filas con cédula duplicada en respaldo', $counts['duplicate_source']],
                ['Cédulas duplicadas en base', $counts['duplicate_database']],
                ['Campos seguros para restaurar', $counts['safe_fields']],
                ['Conflictos no modificables', $counts['conflicts']],
                ['Campos que ya coinciden', $counts['unchanged']],
                ['Valores no útiles en respaldo', $counts['invalid_source']],
                ['Abogados con cambios seguros', count($analysis['updates'])],
            ]
        );
        $this->info('Reporte: ' . $reportPath);
    }

    private function resolveFilePath(string $path): ?string
    {
        if (is_file($path)) {
            return realpath($path) ?: $path;
        }

        $basePath = base_path($path);
        if (is_file($basePath)) {
            return realpath($basePath) ?: $basePath;
        }

        return null;
    }

    private function digitsOnly($value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function comparable($value): string
    {
        $value = str_replace("\xC2\xA0", ' ', trim((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower(trim($value), 'UTF-8');
    }
}
