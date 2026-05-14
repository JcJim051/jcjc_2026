<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use App\Models\MesaBloqueo;
use App\Models\EleccionPuesto;
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
        ]);

        $eleccionId = (int) $data['eleccion_id'];
        $replace = (bool) ($data['replace_existing'] ?? false);
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
}
