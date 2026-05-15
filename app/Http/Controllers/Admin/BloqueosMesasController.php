<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use App\Models\MesaBloqueo;
use App\Models\EleccionPuesto;
use App\Models\EleccionPuestoMeta;
use App\Models\Referido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BloqueosMesasController extends Controller
{
    public function index(Request $request)
    {
        $elecciones = Eleccion::orderByDesc('id')->get(['id', 'nombre']);
        $eleccionId = (int) $request->get('eleccion_id', 0);

        $query = DB::table('mesa_bloqueos as mb')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'mb.eleccion_puesto_id')
            ->join('elecciones as e', 'e.id', '=', 'mb.eleccion_id')
            ->select([
                'mb.id',
                'mb.eleccion_id',
                'e.nombre as eleccion',
                'ep.departamento',
                'ep.municipio',
                'ep.comuna',
                'ep.puesto',
                'mb.mesa_num',
                'mb.origen',
                'mb.lote',
                'mb.fila_origen',
                'mb.created_at',
            ])
            ->orderByDesc('mb.id');

        if ($eleccionId > 0) {
            $query->where('mb.eleccion_id', $eleccionId);
        }

        $bloqueos = $query->get();

        return view('admin.bloqueos_mesas.index', compact('bloqueos', 'elecciones', 'eleccionId'));
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'archivo' => 'required|file|mimes:xlsx,xls',
            'replace_existing' => 'nullable|boolean',
            'modo_importacion' => 'nullable|in:ubicacion,cant_u_objetivo',
        ]);

        $eleccionId = (int) $data['eleccion_id'];
        $replace = (bool) ($data['replace_existing'] ?? false);
        $modo = (string) ($data['modo_importacion'] ?? 'ubicacion');
        $lote = 'excel_' . now()->format('Ymd_His');

        $sheet = IOFactory::load($request->file('archivo')->getRealPath())->getActiveSheet();
        $maxRow = (int) $sheet->getHighestDataRow();

        $created = 0;
        $exists = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            if ($replace) {
                MesaBloqueo::where('eleccion_id', $eleccionId)
                    ->where('origen', 'excel_externo')
                    ->delete();
            }

            if ($modo === 'cant_u_objetivo') {
                [$created, $exists, $errors] = $this->importByCantUObjetivo(
                    $request->file('archivo')->getRealPath(),
                    $eleccionId,
                    $lote
                );
            } else {
                for ($row = 2; $row <= $maxRow; $row++) {
                    $ddRaw = trim((string) $sheet->getCell('A' . $row)->getFormattedValue());
                    $mmRaw = trim((string) $sheet->getCell('B' . $row)->getFormattedValue());
                    $zzRaw = trim((string) $sheet->getCell('C' . $row)->getFormattedValue());
                    $ppRaw = trim((string) $sheet->getCell('D' . $row)->getFormattedValue());
                    $ubicacion = trim((string) $sheet->getCell('M' . $row)->getFormattedValue());

                    if ($ddRaw === '' || $mmRaw === '' || $zzRaw === '' || $ppRaw === '' || $ubicacion === '') {
                        continue;
                    }
                    // "0" en la fuente significa sin mesas asignadas externas; no se bloquea nada.
                    if (in_array($ubicacion, ['0', '0.0', '0,0', '-', 'N/A', 'n/a'], true)) {
                        continue;
                    }

                    $dd = str_pad($ddRaw, 2, '0', STR_PAD_LEFT);
                    $mm = str_pad($mmRaw, 3, '0', STR_PAD_LEFT);
                    $zz = str_pad($zzRaw, 2, '0', STR_PAD_LEFT);
                    $pp = str_pad($ppRaw, 2, '0', STR_PAD_LEFT);

                    $puesto = EleccionPuesto::query()
                        ->where('eleccion_id', $eleccionId)
                        ->where('dd', $dd)
                        ->where('mm', $mm)
                        ->where('zz', $zz)
                        ->where('pp', $pp)
                        ->first();

                    if (!$puesto) {
                        $errors[] = "Fila {$row}: puesto no encontrado ({$dd}-{$mm}-{$zz}-{$pp}).";
                        continue;
                    }

                    $mesas = $this->parseUbicacionEnMesa($ubicacion);
                    if (empty($mesas)) {
                        $errors[] = "Fila {$row}: ubicación inválida \"{$ubicacion}\".";
                        continue;
                    }

                    foreach ($mesas as $mesaNum) {
                        $bloqueo = MesaBloqueo::firstOrCreate([
                            'eleccion_id' => $eleccionId,
                            'eleccion_puesto_id' => $puesto->id,
                            'mesa_num' => (int) $mesaNum,
                        ], [
                            'origen' => 'excel_externo',
                            'lote' => $lote,
                            'fila_origen' => $row,
                            'observacion' => 'Bloqueo importado desde archivo externo.',
                            'created_by' => Auth::id(),
                        ]);

                        if ($bloqueo->wasRecentlyCreated) {
                            $created++;
                        } else {
                            $exists++;
                        }
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('admin.bloqueos_mesas.index', ['eleccion_id' => $eleccionId])
                ->with('error', 'Error importando bloqueos: ' . $e->getMessage());
        }

        $msg = "Importación completada. Creados: {$created}. Ya existentes: {$exists}.";
        if (!empty($errors)) {
            $msg .= ' Filas con error: ' . count($errors) . '.';
        }

        return redirect()->route('admin.bloqueos_mesas.index', ['eleccion_id' => $eleccionId])
            ->with('success', $msg)
            ->with('import_errors', $errors);
    }

    public function destroy(MesaBloqueo $bloqueo)
    {
        $bloqueo->delete();

        return redirect()->route('admin.bloqueos_mesas.index')
            ->with('success', 'Mesa desbloqueada correctamente.');
    }

    private function parseUbicacionEnMesa(string $ubicacion): array
    {
        $txt = mb_strtolower(trim($ubicacion), 'UTF-8');
        $txt = str_replace([' y ', ';'], [',', ','], $txt);
        $parts = array_filter(array_map('trim', explode(',', $txt)));
        $mesas = [];

        foreach ($parts as $part) {
            if (preg_match('/^(\d+)\s*a\s*(la\s*)?(\d+)$/i', $part, $m)) {
                $ini = (int) $m[1];
                $fin = (int) $m[3];
                if ($ini > 0 && $fin >= $ini) {
                    for ($i = $ini; $i <= $fin; $i++) {
                        $mesas[] = $i;
                    }
                    continue;
                }
            }

            if (preg_match('/^\d+$/', $part)) {
                $mesas[] = (int) $part;
                continue;
            }
        }

        $mesas = array_values(array_unique(array_filter($mesas, fn ($m) => $m > 0)));
        sort($mesas);
        return $mesas;
    }

    private function importByCantUObjetivo(string $filePath, int $eleccionId, string $lote): array
    {
        $wb = IOFactory::load($filePath);
        $testigosSheet = null;
        $divipolSheet = null;

        foreach ($wb->getWorksheetIterator() as $sheet) {
            $name = mb_strtolower((string) $sheet->getTitle(), 'UTF-8');
            if (str_contains($name, 'testigos')) {
                $testigosSheet = $sheet;
            }
            if (str_contains($name, 'divipol')) {
                $divipolSheet = $sheet;
            }
        }

        if (!$testigosSheet || !$divipolSheet) {
            return [0, 0, ['No se encontraron las hojas requeridas (TESTIGOS y DIVIPOL).']];
        }

        $errors = [];
        $created = 0;
        $exists = 0;

        $puestosDivipol = [];
        $maxRowDivipol = (int) $divipolSheet->getHighestDataRow();
        for ($row = 2; $row <= $maxRowDivipol; $row++) {
            $dd = str_pad(trim((string) $divipolSheet->getCell('A' . $row)->getFormattedValue()), 2, '0', STR_PAD_LEFT);
            $mm = str_pad(trim((string) $divipolSheet->getCell('B' . $row)->getFormattedValue()), 3, '0', STR_PAD_LEFT);
            $zz = str_pad(trim((string) $divipolSheet->getCell('C' . $row)->getFormattedValue()), 2, '0', STR_PAD_LEFT);
            $pp = str_pad(trim((string) $divipolSheet->getCell('D' . $row)->getFormattedValue()), 2, '0', STR_PAD_LEFT);
            $puesto = trim((string) $divipolSheet->getCell('G' . $row)->getFormattedValue());
            $comuna = trim((string) $divipolSheet->getCell('H' . $row)->getFormattedValue());

            if ($dd === '' || $mm === '' || $zz === '' || $pp === '' || $puesto === '') {
                continue;
            }

            $puestosDivipol[] = [
                'row' => $row,
                'dd' => $dd,
                'mm' => $mm,
                'zz' => $zz,
                'pp' => $pp,
                'puesto' => $puesto,
                'puesto_norm' => $this->normalizeText($puesto),
                'comuna' => $comuna,
                'comuna_norm' => $this->normalizeText($comuna),
            ];
        }

        $maxRowTestigos = (int) $testigosSheet->getHighestDataRow();
        $comunaActual = '';
        for ($row = 1; $row <= $maxRowTestigos; $row++) {
            $colA = trim((string) $testigosSheet->getCell('A' . $row)->getFormattedValue());
            $cantURaw = trim((string) $testigosSheet->getCell('C' . $row)->getFormattedValue());

            if ($colA === '' || $cantURaw === '') {
                continue;
            }
            if (preg_match('/^total/i', $colA)) {
                continue;
            }
            if (preg_match('/^\d+\s*comuna/i', $colA)) {
                $comunaActual = $colA;
                continue;
            }

            if (!is_numeric(str_replace(',', '.', $cantURaw))) {
                continue;
            }
            $objetivo = (int) floor((float) str_replace(',', '.', $cantURaw));
            if ($objetivo < 0) {
                $objetivo = 0;
            }

            $puestoNorm = $this->normalizeText($colA);
            $comunaNorm = $this->normalizeText($comunaActual);
            $candidatos = array_values(array_filter($puestosDivipol, function ($p) use ($puestoNorm) {
                return $p['puesto_norm'] === $puestoNorm;
            }));
            if (count($candidatos) > 1 && $comunaNorm !== '') {
                $filtrados = array_values(array_filter($candidatos, function ($p) use ($comunaNorm) {
                    return $p['comuna_norm'] === $comunaNorm;
                }));
                if (!empty($filtrados)) {
                    $candidatos = $filtrados;
                }
            }

            if (count($candidatos) !== 1) {
                $errors[] = "Fila {$row}: puesto ambiguo o no encontrado \"{$colA}\".";
                continue;
            }
            $c = $candidatos[0];

            $puestoModel = EleccionPuesto::query()
                ->where('eleccion_id', $eleccionId)
                ->where('dd', $c['dd'])
                ->where('mm', $c['mm'])
                ->where('zz', $c['zz'])
                ->where('pp', $c['pp'])
                ->first();
            if (!$puestoModel) {
                $errors[] = "Fila {$row}: puesto no existe en elección seleccionada ({$c['dd']}-{$c['mm']}-{$c['zz']}-{$c['pp']}).";
                continue;
            }

            EleccionPuestoMeta::updateOrCreate(
                [
                    'eleccion_id' => $eleccionId,
                    'eleccion_puesto_id' => $puestoModel->id,
                ],
                [
                    'meta_pactada' => $objetivo,
                    'origen' => 'excel_cant_u',
                    'lote' => $lote,
                ]
            );

            $todasMesas = DB::table('eleccion_mesas')
                ->where('eleccion_id', $eleccionId)
                ->where('eleccion_puesto_id', $puestoModel->id)
                ->pluck('mesa_num')
                ->map(fn ($m) => (int) $m)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if (empty($todasMesas)) {
                $errors[] = "Fila {$row}: puesto sin mesas cargadas en elección.";
                continue;
            }

            $bloqueadasActuales = DB::table('mesa_bloqueos')
                ->where('eleccion_id', $eleccionId)
                ->where('eleccion_puesto_id', $puestoModel->id)
                ->pluck('mesa_num')
                ->map(fn ($m) => (int) $m)
                ->unique()
                ->values()
                ->all();

            $habilitadas = array_values(array_diff($todasMesas, $bloqueadasActuales));
            $aBloquear = max(count($habilitadas) - $objetivo, 0);
            if ($aBloquear <= 0) {
                if ($objetivo > count($habilitadas)) {
                    $errors[] = "Fila {$row}: objetivo {$objetivo} no alcanzable; habilitadas actuales {$this->countInt($habilitadas)}.";
                }
                continue;
            }

            // Priorizamos bloquear mesas habilitadas SIN referidos; solo si no alcanza,
            // completamos con mesas habilitadas que ya tengan referidos.
            $mesasConReferidos = Referido::query()
                ->where('eleccion_id', $eleccionId)
                ->where('eleccion_puesto_id', $puestoModel->id)
                ->where('estado', '<>', 'rechazado')
                ->pluck('mesa_num')
                ->map(fn ($m) => (int) $m)
                ->unique()
                ->values()
                ->all();

            $habilitadasLibres = array_values(array_diff($habilitadas, $mesasConReferidos));
            $habilitadasOcupadas = array_values(array_intersect($habilitadas, $mesasConReferidos));

            $shuffleStable = function (array $mesas) use ($eleccionId, $puestoModel) {
                usort($mesas, function ($a, $b) use ($eleccionId, $puestoModel) {
                    $ha = sha1($eleccionId . '|' . $puestoModel->id . '|' . $a . '|cant_u_seed');
                    $hb = sha1($eleccionId . '|' . $puestoModel->id . '|' . $b . '|cant_u_seed');
                    return $ha <=> $hb;
                });
                return $mesas;
            };

            $habilitadasLibres = $shuffleStable($habilitadasLibres);
            $habilitadasOcupadas = $shuffleStable($habilitadasOcupadas);

            $seleccion = [];
            if (!empty($habilitadasLibres)) {
                $seleccion = array_slice($habilitadasLibres, 0, min($aBloquear, count($habilitadasLibres)));
            }
            if (count($seleccion) < $aBloquear && !empty($habilitadasOcupadas)) {
                $faltan = $aBloquear - count($seleccion);
                $seleccion = array_merge($seleccion, array_slice($habilitadasOcupadas, 0, $faltan));
            }

            foreach ($seleccion as $mesaNum) {
                $bloqueo = MesaBloqueo::firstOrCreate([
                    'eleccion_id' => $eleccionId,
                    'eleccion_puesto_id' => $puestoModel->id,
                    'mesa_num' => (int) $mesaNum,
                ], [
                    'origen' => 'excel_objetivo_cant_u',
                    'lote' => $lote,
                    'fila_origen' => $row,
                    'observacion' => 'Bloqueo por ajuste objetivo CANT U.',
                    'created_by' => Auth::id(),
                ]);

                if ($bloqueo->wasRecentlyCreated) {
                    $created++;
                } else {
                    $exists++;
                }
            }
        }

        return [$created, $exists, $errors];
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);
        return trim((string) $value);
    }

    private function countInt(array $values): int
    {
        return count($values);
    }
}
