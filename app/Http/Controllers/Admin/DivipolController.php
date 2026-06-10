<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DivipolController extends Controller
{
    public function index(Request $request)
    {
        $elecciones = Eleccion::query()
            ->where('estado', 'activa')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $requestedElectionId = (int) $request->input('eleccion_id', 0);
        $eleccion = $this->resolveActiveElection($requestedElectionId ?: null, $elecciones);

        $puestos = collect();
        if ($eleccion) {
            $puestos = DB::table('eleccion_puestos')
                ->where('eleccion_id', $eleccion->id)
                ->orderBy('departamento')
                ->orderBy('municipio')
                ->orderBy('zz')
                ->orderBy('pp')
                ->get();
        }

        return view('admin.divipol.index', compact('elecciones', 'eleccion', 'puestos'));
    }

    public function export(Eleccion $eleccion)
    {
        $this->ensureActive($eleccion);

        $puestos = DB::table('eleccion_puestos')
            ->where('eleccion_id', $eleccion->id)
            ->orderBy('dd')
            ->orderBy('mm')
            ->orderBy('zz')
            ->orderBy('pp')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DIVIPOL');

        $headers = [
            'dd',
            'mm',
            'zz',
            'pp',
            'codigo_puesto',
            'departamento',
            'municipio',
            'puesto',
            'comuna',
            'direccion',
            'mesas',
        ];

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex + 1, 1, $header);
        }

        foreach ($puestos as $rowIndex => $puesto) {
            $codigoPuesto = trim((string) $puesto->codigo_puesto);
            if ($codigoPuesto === '') {
                $codigoPuesto = $puesto->dd . $puesto->mm . $puesto->zz . $puesto->pp;
            }

            $row = [
                $puesto->dd,
                $puesto->mm,
                $puesto->zz,
                $puesto->pp,
                $codigoPuesto,
                $puesto->departamento,
                $puesto->municipio,
                $puesto->puesto,
                $puesto->comuna,
                $puesto->direccion,
                $puesto->mesas_total,
            ];

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
        $lastRow = max(1, $sheet->getHighestRow());
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('INSTRUCCIONES');
        $instructions->fromArray([
            ['Elección', $eleccion->nombre],
            ['Fecha de descarga', now()->format('Y-m-d H:i:s')],
            ['Uso', 'Actualice los datos necesarios y vuelva a cargar este archivo desde el módulo DIVIPOL.'],
            ['Importante', 'No cambie los encabezados dd, mm, zz, pp, departamento, municipio, puesto, comuna, direccion y mesas.'],
            ['Comuna', 'Si el puesto no pertenece a una comuna, use SIN COMUNA o ZONA RURAL.'],
        ], null, 'A1');
        $instructions->getStyle('A1:A5')->getFont()->setBold(true);
        $instructions->getColumnDimension('A')->setWidth(22);
        $instructions->getColumnDimension('B')->setWidth(95);
        $instructions->getStyle('A1:B5')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $eleccion->nombre);
        $filename = 'divipol_' . trim($safeName, '_') . '_' . now()->format('Y-m-d_His') . '.xlsx';
        $temporaryFile = tempnam(sys_get_temp_dir(), 'divipol_');
        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temporaryFile, $filename)->deleteFileAfterSend(true);
    }

    public function update(Request $request, Eleccion $eleccion)
    {
        $this->ensureActive($eleccion);

        $request->validate([
            'divipol' => 'required|file|mimes:xlsx',
        ]);

        $path = $request->file('divipol')->store('divipol_uploads', 'local');
        $fullPath = storage_path('app/' . $path);

        $exitCode = Artisan::call('divipol:import-u101', [
            'file' => $fullPath,
            'eleccion_id' => $eleccion->id,
            '--dd' => $eleccion->alcance_dd ?: null,
            '--mm' => $eleccion->alcance_mm ?: null,
        ]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return redirect()
                ->route('admin.divipol.index', ['eleccion_id' => $eleccion->id])
                ->with('error', $output !== '' ? $output : 'No se pudo actualizar la DIVIPOL.');
        }

        return redirect()
            ->route('admin.divipol.index', ['eleccion_id' => $eleccion->id])
            ->with('success', 'DIVIPOL actualizada para ' . $eleccion->nombre . '. ' . $output);
    }

    private function resolveActiveElection(?int $requestedId, $elecciones): ?Eleccion
    {
        if ($requestedId) {
            $selected = $elecciones->firstWhere('id', $requestedId);
            if ($selected) {
                return $selected;
            }
        }

        return $elecciones->first();
    }

    private function ensureActive(Eleccion $eleccion): void
    {
        abort_unless($eleccion->estado === 'activa', 404);
    }
}
