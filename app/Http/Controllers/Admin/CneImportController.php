<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CneImportController extends Controller
{
    public function index()
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        return view('admin.cne_import.index', compact('elecciones'));
    }

    public function importarPostulados(Request $request)
    {
        return $this->importar($request, 'validado', 'postulado');
    }

    public function importarAcreditados(Request $request)
    {
        return $this->importar($request, 'postulado', 'acreditado');
    }

    private function importar(Request $request, string $estadoActual, string $estadoNuevo)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'archivo' => 'required|file|mimes:xlsx',
        ]);

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return back()->with('error', 'PhpSpreadsheet no esta instalado.');
        }

        $filePath = $request->file('archivo')->getRealPath();

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, true);
        $header = $rows[1] ?? [];

        $colMap = $this->mapColumns($header);
        if (!isset($colMap['identificacion'])) {
            return back()->with('error', 'No se encontro la columna Identificacion.');
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

    private function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $col => $name) {
            $key = $this->norm($name);
            if ($key === 'identificacion') {
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
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
