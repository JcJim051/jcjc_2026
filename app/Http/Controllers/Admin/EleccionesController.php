<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EleccionesController extends Controller
{
    public function index()
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        return view('admin.elecciones.index', compact('elecciones'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:100',
            'fecha' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'meta_testigos_pct' => 'nullable|integer|min:0|max:100',
            'alcance_tipo' => 'nullable|string|max:50',
            'alcance_dd' => 'nullable|string|max:2',
            'alcance_mm' => 'nullable|string|max:255',
            'divipol' => 'nullable|file|mimes:xlsx',
        ]);
        $alcanceDd = $this->normalizeDd($data['alcance_dd'] ?? null);
        $alcanceMm = $this->normalizeMmCsv($data['alcance_mm'] ?? null);

        $eleccion = Eleccion::create([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'fecha' => $data['fecha'] ?? null,
            'estado' => $data['estado'],
            'alcance_tipo' => $data['alcance_tipo'] ?? null,
            'alcance_dd' => $alcanceDd,
            'alcance_mm' => $alcanceMm,
            'meta_testigos_pct' => $data['meta_testigos_pct'] ?? null,
        ]);

        if ($request->hasFile('divipol')) {
            $result = $this->importDivipol($request->file('divipol'), $eleccion->id);
            if ($result !== true) {
                return redirect()->route('admin.elecciones.index')
                    ->with('error', 'Eleccion creada, pero fallo la importacion DIVIPOL: ' . $result);
            }
        }

        return redirect()->route('admin.elecciones.index')
            ->with('success', 'Eleccion creada correctamente.');
    }

    public function import(Request $request, Eleccion $eleccion)
    {
        $request->validate([
            'divipol' => 'required|file|mimes:xlsx',
        ]);

        $result = $this->importDivipol($request->file('divipol'), $eleccion->id);
        if ($result !== true) {
            return redirect()->route('admin.elecciones.index')
                ->with('error', 'Fallo la importacion DIVIPOL: ' . $result);
        }

        return redirect()->route('admin.elecciones.index')
            ->with('success', 'DIVIPOL importado correctamente para la eleccion ' . $eleccion->id);
    }

    public function downloadTemplate()
    {
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

        $example = [
            '50',
            '001',
            '01',
            '05',
            '500010105',
            'META',
            'VILLAVICENCIO',
            'IE JUAN PABLO II SEDE CHAPINERO BAJO',
            '01COMUNA 1',
            'CLL 48A # 51 - 70',
            '12',
        ];

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex + 1, 1, $header);
            $sheet->setCellValueExplicitByColumnAndRow(
                $columnIndex + 1,
                2,
                $example[$columnIndex],
                DataType::TYPE_STRING
            );
        }

        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle("A1:{$lastColumn}2")->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}2");

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $help = $spreadsheet->createSheet();
        $help->setTitle('INSTRUCCIONES');
        $helpRows = [
            ['Campo', 'Descripción', 'Obligatorio'],
            ['dd', 'Código DANE del departamento, conservando dos dígitos.', 'Sí'],
            ['mm', 'Código DANE del municipio, conservando tres dígitos.', 'Sí'],
            ['zz', 'Código de zona electoral, conservando dos dígitos.', 'Sí'],
            ['pp', 'Código del puesto dentro de la zona, conservando dos dígitos.', 'Sí'],
            ['codigo_puesto', 'Código completo del puesto. Si no existe, puede dejarse vacío.', 'No'],
            ['departamento', 'Nombre oficial del departamento.', 'Sí'],
            ['municipio', 'Nombre oficial del municipio.', 'Sí'],
            ['puesto', 'Nombre oficial del puesto de votación.', 'Sí'],
            ['comuna', 'Comuna, zona rural o clasificación territorial vigente para esta elección. Si no aplica, usar SIN COMUNA o ZONA RURAL.', 'Sí'],
            ['direccion', 'Dirección oficial del puesto para esta elección.', 'Sí'],
            ['mesas', 'Cantidad total de mesas habilitadas en el puesto.', 'Sí'],
        ];
        $help->fromArray($helpRows, null, 'A1');
        $help->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $help->getStyle('A1:C1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F4E78');
        $help->getStyle('A1:C12')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $help->getColumnDimension('A')->setWidth(22);
        $help->getColumnDimension('B')->setWidth(75);
        $help->getColumnDimension('C')->setWidth(15);
        $help->freezePane('A2');

        $filename = 'plantilla_divipol_por_eleccion.xlsx';
        $temporaryFile = tempnam(sys_get_temp_dir(), 'divipol_template_');
        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temporaryFile, $filename)->deleteFileAfterSend(true);
    }

    public function update(Request $request, Eleccion $eleccion)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:100',
            'fecha' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'meta_testigos_pct' => 'nullable|integer|min:0|max:100',
            'alcance_tipo' => 'nullable|string|max:50',
            'alcance_dd' => 'nullable|string|max:2',
            'alcance_mm' => 'nullable|string|max:255',
        ]);
        $alcanceDd = $this->normalizeDd($data['alcance_dd'] ?? null);
        $alcanceMm = $this->normalizeMmCsv($data['alcance_mm'] ?? null);

        $eleccion->update([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'fecha' => $data['fecha'] ?? null,
            'estado' => $data['estado'],
            'alcance_tipo' => $data['alcance_tipo'] ?? null,
            'alcance_dd' => $alcanceDd,
            'alcance_mm' => $alcanceMm,
            'meta_testigos_pct' => $data['meta_testigos_pct'] ?? null,
        ]);

        return redirect()->route('admin.elecciones.index')
            ->with('success', 'Eleccion actualizada correctamente.');
    }

    private function importDivipol($file, int $eleccionId)
    {
        $path = $file->store('divipol_uploads', 'local');
        $fullPath = storage_path('app/' . $path);

        $eleccion = Eleccion::find($eleccionId);

        $exitCode = Artisan::call('divipol:import-u101', [
            'file' => $fullPath,
            'eleccion_id' => $eleccionId,
            '--dd' => $eleccion?->alcance_dd ?: null,
            '--mm' => $eleccion?->alcance_mm ?: null,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return $output !== '' ? $output : 'Error desconocido al ejecutar el comando.';
        }

        return true;
    }

    private function normalizeDd(?string $dd): ?string
    {
        if ($dd === null) {
            return null;
        }
        $dd = trim($dd);
        if ($dd === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $dd)) {
            return str_pad($dd, 2, '0', STR_PAD_LEFT);
        }
        return $dd;
    }

    private function normalizeMmCsv(?string $mm): ?string
    {
        if ($mm === null) {
            return null;
        }
        $mm = trim($mm);
        if ($mm === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', $mm));
        $out = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\d+$/', $part)) {
                $part = str_pad($part, 3, '0', STR_PAD_LEFT);
            }
            $out[] = $part;
        }
        $out = array_values(array_unique($out));
        return $out ? implode(',', $out) : null;
    }
}
