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

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
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
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
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

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return back()->with('error', 'PhpSpreadsheet no esta instalado.');
        }

        $filePath = $request->file('archivo')->getRealPath();

        $ext = strtolower((string) $request->file('archivo')->getClientOriginalExtension());
        if (in_array($ext, ['csv', 'txt'], true)) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
            $reader->setDelimiter(';');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
        } else {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        }
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, true);
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
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
