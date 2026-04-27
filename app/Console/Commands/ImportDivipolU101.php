<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDivipolU101 extends Command
{
    protected $signature = 'divipol:import-u101 {file} {eleccion_id} {--sheet=} {--dd=} {--mm=}';
    protected $description = 'Importa DIVIPOL U101 (Excel) y genera puestos y mesas por eleccion.';

    public function handle()
    {
        $file = $this->argument('file');
        $eleccionId = (int) $this->argument('eleccion_id');
        $sheetName = $this->option('sheet');
        $ddFilter = $this->normalizeCode($this->option('dd'), 2);
        $mmFilter = $this->normalizeList($this->option('mm'), 3);

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            $this->error('PhpSpreadsheet no esta instalado. Agrega phpoffice/phpspreadsheet en composer.json y ejecuta composer install.');
            return 1;
        }

        if (!is_file($file)) {
            $this->error('Archivo no encontrado: ' . $file);
            return 1;
        }

        $this->info('Cargando archivo: ' . $file);

        // Intentar evitar errores de memoria en XLSX grandes.
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '-1');
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        // Filtro para leer solo columnas relevantes (reduce memoria).
        $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function readCell($column, $row, $worksheetName = '')
            {
                // Columnas A a P (hasta 16) donde viene el layout U101.
                return $column >= 'A' && $column <= 'P';
            }
        });

        $spreadsheet = $reader->load($file);
        $sheet = $sheetName ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();

        if (!$sheet) {
            $this->error('No se encontro la hoja solicitada.');
            return 1;
        }

        // Detectar encabezado leyendo solo primeras filas.
        $headerRowIndex = $this->findHeaderRowFromSheet($sheet, 40);

        if ($headerRowIndex === null) {
            $this->error('No se encontro la fila de encabezados con dd/mm/zz/pp/mesas.');
            return 1;
        }

        $header = $this->readRow($sheet, $headerRowIndex);
        $colMap = $this->buildColumnMap($header);

        $required = ['dd', 'mm', 'zz', 'pp', 'mesas'];
        foreach ($required as $req) {
            if (!isset($colMap[$req])) {
                $this->error('Falta columna requerida: ' . $req);
                return 1;
            }
        }

        $this->info('Encabezado detectado en fila: ' . $headerRowIndex);

        $batch = [];
        $batchSize = 1000;
        $puestosCount = 0;
        $mesasCount = 0;

        DB::beginTransaction();
        try {
            $highestRow = $sheet->getHighestRow();
            for ($idx = $headerRowIndex + 1; $idx <= $highestRow; $idx++) {
                $row = $this->readRow($sheet, $idx);

                $dd = $this->val($row, $colMap['dd']);
                $mm = $this->val($row, $colMap['mm']);
                $zz = $this->val($row, $colMap['zz']);
                $pp = $this->val($row, $colMap['pp']);

                if ($dd === '' || $mm === '' || $zz === '' || $pp === '') {
                    continue;
                }

                $dd = str_pad(preg_replace('/\D/', '', $dd), 2, '0', STR_PAD_LEFT);
                $mm = str_pad(preg_replace('/\D/', '', $mm), 3, '0', STR_PAD_LEFT);
                $zz = str_pad(preg_replace('/\D/', '', $zz), 2, '0', STR_PAD_LEFT);
                $pp = str_pad(preg_replace('/\D/', '', $pp), 2, '0', STR_PAD_LEFT);

                $mesasTotal = (int) preg_replace('/\D/', '', $this->val($row, $colMap['mesas']));

                if ($ddFilter !== '' && $dd !== $ddFilter) {
                    continue;
                }
                if (!empty($mmFilter) && !in_array($mm, $mmFilter, true)) {
                    continue;
                }

                if ($mesasTotal <= 0) {
                    continue;
                }

                $data = [
                    'eleccion_id' => $eleccionId,
                    'dd' => $dd,
                    'mm' => $mm,
                    'zz' => $zz,
                    'pp' => $pp,
                    'codigo_puesto' => $this->val($row, $colMap['codigo_puesto'] ?? null),
                    'departamento' => $this->val($row, $colMap['departamento'] ?? null),
                    'municipio' => $this->val($row, $colMap['municipio'] ?? null),
                    'puesto' => $this->val($row, $colMap['puesto'] ?? null),
                    'comuna' => $this->val($row, $colMap['comuna'] ?? null),
                    'direccion' => $this->val($row, $colMap['direccion'] ?? null),
                    'mesas_total' => $mesasTotal,
                    'updated_at' => now(),
                ];

                $exists = DB::table('eleccion_puestos')
                    ->where('eleccion_id', $eleccionId)
                    ->where('dd', $dd)
                    ->where('mm', $mm)
                    ->where('zz', $zz)
                    ->where('pp', $pp)
                    ->first();

                if ($exists) {
                    DB::table('eleccion_puestos')
                        ->where('id', $exists->id)
                        ->update($data);
                    $puestoId = $exists->id;
                } else {
                    $data['created_at'] = now();
                    $puestoId = DB::table('eleccion_puestos')->insertGetId($data);
                    $puestosCount++;
                }

                for ($i = 1; $i <= $mesasTotal; $i++) {
                    $mesaKey = $eleccionId . '-' . $dd . $mm . $zz . $pp . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                    $batch[] = [
                        'eleccion_id' => $eleccionId,
                        'eleccion_puesto_id' => $puestoId,
                        'mesa_num' => $i,
                        'mesa_key' => $mesaKey,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::table('eleccion_mesas')->insertOrIgnore($batch);
                        $mesasCount += count($batch);
                        $batch = [];
                    }
                }
            }

            if (!empty($batch)) {
                DB::table('eleccion_mesas')->insertOrIgnore($batch);
                $mesasCount += count($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error al importar: ' . $e->getMessage());
            return 1;
        }

        $this->info('Puestos creados/actualizados: ' . $puestosCount);
        $this->info('Mesas insertadas (intento): ' . $mesasCount);

        return 0;
    }

    private function findHeaderRowFromSheet($sheet, int $maxRows): ?int
    {
        $limit = min($maxRows, $sheet->getHighestRow());
        for ($i = 1; $i <= $limit; $i++) {
            $row = $this->readRow($sheet, $i);
            if (empty($row)) {
                continue;
            }
            $values = array_map([$this, 'norm'], $row);
            $joined = implode('|', $values);
            if (strpos($joined, 'dd') !== false && strpos($joined, 'mm') !== false && strpos($joined, 'zz') !== false && strpos($joined, 'pp') !== false) {
                if (strpos($joined, 'mesas') !== false) {
                    return $i;
                }
            }
        }
        return null;
    }

    private function readRow($sheet, int $rowIndex): array
    {
        $row = [];
        $cellIterator = $sheet->getRowIterator($rowIndex, $rowIndex)->current()->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $row[$cell->getColumn()] = $cell->getValue();
        }
        return $row;
    }

    private function buildColumnMap(array $header): array
    {
        $map = [];
        foreach ($header as $col => $name) {
            $norm = $this->norm($name);
            if ($norm === 'dd') $map['dd'] = $col;
            elseif ($norm === 'mm') $map['mm'] = $col;
            elseif ($norm === 'zz') $map['zz'] = $col;
            elseif ($norm === 'pp') $map['pp'] = $col;
            elseif ($norm === 'codigo de puesto' || $norm === 'codigo_puesto') $map['codigo_puesto'] = $col;
            elseif ($norm === 'departamento') $map['departamento'] = $col;
            elseif ($norm === 'municipio') $map['municipio'] = $col;
            elseif ($norm === 'puesto') $map['puesto'] = $col;
            elseif ($norm === 'comuna') $map['comuna'] = $col;
            elseif ($norm === 'direccion') $map['direccion'] = $col;
            elseif ($norm === 'mesas') $map['mesas'] = $col;
        }
        return $map;
    }

    private function norm($value): string
    {
        $value = is_string($value) ? $value : '';
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(['\n', '\r', '\t'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($trans !== false) {
            $value = $trans;
        }

        return trim($value);
    }

    private function val(array $row, ?string $col): string
    {
        if ($col === null) {
            return '';
        }
        $v = $row[$col] ?? '';
        if ($v === null) return '';
        return trim((string) $v);
    }

    private function normalizeCode($value, int $len): string
    {
        if ($value === null) {
            return '';
        }
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '') {
            return '';
        }
        return str_pad($digits, $len, '0', STR_PAD_LEFT);
    }

    private function normalizeList($value, int $len): array
    {
        if ($value === null) {
            return [];
        }
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        $parts = preg_split('/[,\s;]+/', $value);
        $out = [];
        foreach ($parts as $part) {
            $code = $this->normalizeCode($part, $len);
            if ($code !== '') {
                $out[] = $code;
            }
        }
        return array_values(array_unique($out));
    }
}
