<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\ComisionMesaComentario;
use App\Models\Eleccion;
use App\Models\PuestoReporte;
use App\Traits\BuildsMesaAlertas;
use App\Traits\EleccionScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MesaReporteDashboardController extends Controller
{
    use EleccionScope;
    use BuildsMesaAlertas;

    public function updateSteps(Request $request)
    {
        $eleccionId = $this->resolveEleccionId((int) $request->get('eleccion_id'));
        $eleccion = Eleccion::findOrFail($eleccionId);

        $data = $request->validate([
            'eleccion_id' => ['required', 'integer', 'exists:elecciones,id'],
            'habilitar_afluencia' => ['nullable', 'boolean'],
            'habilitar_datos_e14' => ['nullable', 'boolean'],
            'habilitar_informacion_final' => ['nullable', 'boolean'],
            'habilitar_foto_e14' => ['nullable', 'boolean'],
        ]);

        $eleccion->update([
            'habilitar_afluencia' => $request->boolean('habilitar_afluencia'),
            'habilitar_datos_e14' => $request->boolean('habilitar_datos_e14'),
            'habilitar_informacion_final' => $request->boolean('habilitar_informacion_final'),
            'habilitar_foto_e14' => $request->boolean('habilitar_foto_e14'),
        ]);

        return back()->with('success', 'Visibilidad de pasos actualizada correctamente.');
    }

    public function index(Request $request)
    {
        $data = $this->buildDashboardData($request);
        return view('admin.mesa_reportes.dashboard', $data);
    }

    public function afluencia(Request $request)
    {
        $data = $this->buildDashboardData($request);
        return view('admin.mesa_reportes.afluencia', $data);
    }

    public function exportAfluenciaPuestos(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = collect($data['resumenPuesto'] ?? [])->values();
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Afluencia por puesto');

        $headers = [
            'Municipio',
            'Comuna',
            'Puesto',
            'Mesas total',
            'Mesas con reporte',
            'Avance %',
            'Personas 9:00',
            'Personas 11:00',
            'Personas 2:00',
            'Total personas',
            'E14',
            'Cierre',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, $row['puesto'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, (int) ($row['mesas_total'] ?? 0));
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, (int) ($row['afluencia'] ?? 0));
            $sheet->setCellValueByColumnAndRow(6, $rowNumber, (float) ($row['pct_afluencia'] ?? 0));
            $sheet->setCellValueByColumnAndRow(7, $rowNumber, (int) ($row['personas_9'] ?? 0));
            $sheet->setCellValueByColumnAndRow(8, $rowNumber, (int) ($row['personas_11'] ?? 0));
            $sheet->setCellValueByColumnAndRow(9, $rowNumber, (int) ($row['personas_14'] ?? 0));
            $sheet->setCellValueByColumnAndRow(10, $rowNumber, (int) ($row['personas_total'] ?? 0));
            $sheet->setCellValueByColumnAndRow(11, $rowNumber, (int) ($row['e14'] ?? 0));
            $sheet->setCellValueByColumnAndRow(12, $rowNumber, (int) ($row['control'] ?? 0));
            $rowNumber++;
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'afluencia_puestos_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'afluencia_puestos_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportAfluenciaComunas(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = collect($data['resumenComunaAfluencia'] ?? [])->values();
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Afluencia por comuna');

        $headers = [
            'Municipio',
            'Comuna',
            'Mesas total',
            'Mesas con reporte',
            '9:00',
            '11:00',
            '2:00',
            'Total personas',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, (int) ($row['mesas_total'] ?? 0));
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, (int) ($row['mesas_con_reporte'] ?? 0));
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, (int) ($row['personas_9'] ?? 0));
            $sheet->setCellValueByColumnAndRow(6, $rowNumber, (int) ($row['personas_11'] ?? 0));
            $sheet->setCellValueByColumnAndRow(7, $rowNumber, (int) ($row['personas_14'] ?? 0));
            $sheet->setCellValueByColumnAndRow(8, $rowNumber, (int) ($row['personas_total'] ?? 0));
            $rowNumber++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'afluencia_comunas_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'afluencia_comunas_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportAfluenciaMesas(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = collect($data['rows'] ?? [])->map(function ($row) {
            $personas9 = (int) ($row->afluencia_9 ?? 0);
            $personas11 = (int) ($row->afluencia_11 ?? 0);
            $personas14 = (int) ($row->afluencia_14 ?? 0);

            return [
                'municipio' => (string) ($row->municipio ?? ''),
                'comuna' => trim((string) ($row->comuna ?? '')) !== '' ? (string) $row->comuna : 'SIN COMUNA',
                'puesto' => (string) ($row->puesto ?? ''),
                'mesa' => (string) ($row->mesa_num ?? ''),
                'afluencia_9' => $personas9,
                'afluencia_11' => $personas11,
                'afluencia_14' => $personas14,
                'total_personas' => $personas9 + $personas11 + $personas14,
                'estado' => (!is_null($row->afluencia_9) && !is_null($row->afluencia_11) && !is_null($row->afluencia_14))
                    ? 'Completa'
                    : ((!is_null($row->afluencia_9) || !is_null($row->afluencia_11) || !is_null($row->afluencia_14)) ? 'Parcial' : 'Sin reporte'),
            ];
        })->values();
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Afluencia por mesa');

        $headers = [
            'Municipio',
            'Comuna',
            'Puesto',
            'Mesa',
            '9:00',
            '11:00',
            '2:00',
            'Total personas',
            'Estado',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio']);
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna']);
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, $row['puesto']);
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, $row['mesa']);
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, $row['afluencia_9']);
            $sheet->setCellValueByColumnAndRow(6, $rowNumber, $row['afluencia_11']);
            $sheet->setCellValueByColumnAndRow(7, $rowNumber, $row['afluencia_14']);
            $sheet->setCellValueByColumnAndRow(8, $rowNumber, $row['total_personas']);
            $sheet->setCellValueByColumnAndRow(9, $rowNumber, $row['estado']);
            $rowNumber++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'afluencia_mesas_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'afluencia_mesas_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportAfluenciaTestigosPuestos(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = collect($data['resumenPuestoTestigos'] ?? [])->values();
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Testigos por puesto');

        $headers = ['Municipio', 'Comuna', 'Puesto', 'Mesas total', 'Testigos presentes'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, $row['puesto'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, (int) ($row['mesas_total'] ?? 0));
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, (int) ($row['testigos_presentes'] ?? 0));
            $rowNumber++;
        }

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'testigos_presentes_puestos_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'testigos_presentes_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function e14(Request $request)
    {
        $data = $this->buildDashboardData($request);
        return view('admin.mesa_reportes.e14', $data);
    }

    public function mesas(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $data['alertRows'] = $this->buildMesaAlertRows(collect($data['rows'] ?? []));

        return view('admin.mesa_reportes.mesas', $data);
    }

    public function showMesa(Request $request, int $mesa)
    {
        $data = $this->buildDashboardData($request);
        $alertRows = $this->buildMesaAlertRows(collect($data['rows'] ?? []));
        $mesaRow = $alertRows->firstWhere('mesa_id', $mesa);

        abort_unless($mesaRow, 404);

        $coordinadores = $this->coordinadoresPorPuesto((int) $data['eleccionId'], (int) $mesaRow['puesto_id']);
        $reclamacionCandidato = null;
        if (!empty($mesaRow['row']->reclamacion_candidato_id)) {
            $candidate = Candidato::find((int) $mesaRow['row']->reclamacion_candidato_id);
            if ($candidate) {
                $reclamacionCandidato = trim(($candidate->codigo ? $candidate->codigo . ' - ' : '') . $candidate->nombre);
            }
        }

        $e14DetalleRows = $this->buildAdminE14DetailRows((int) $data['eleccionId'], $mesaRow['row']->e14_detalle);
        $comentarios = ComisionMesaComentario::query()
            ->where('eleccion_id', $data['eleccionId'])
            ->where('eleccion_mesa_id', $mesa)
            ->latest()
            ->get();

        return view('admin.mesa_reportes.show', [
            'eleccionId' => $data['eleccionId'],
            'eleccionOperativa' => $data['eleccionOperativa'],
            'mesa' => $mesaRow,
            'coordinadores' => $coordinadores,
            'reclamacionCandidato' => $reclamacionCandidato,
            'e14DetalleRows' => $e14DetalleRows,
            'comentarios' => $comentarios,
        ]);
    }

    public function exportE14Comunas(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = collect($data['resumenComunaE14'] ?? [])->values();
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('E14 por comuna');

        $headers = ['Municipio', 'Comuna', 'Mesas total', 'E14', 'Control', 'Reconteos', 'Reclamaciones', 'Fotos'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, (int) ($row['mesas_total'] ?? 0));
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, (int) ($row['e14'] ?? 0));
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, (int) ($row['control'] ?? 0));
            $sheet->setCellValueByColumnAndRow(6, $rowNumber, (int) ($row['reconteos'] ?? 0));
            $sheet->setCellValueByColumnAndRow(7, $rowNumber, (int) ($row['reclamaciones'] ?? 0));
            $sheet->setCellValueByColumnAndRow(8, $rowNumber, (int) ($row['fotos'] ?? 0));
            $rowNumber++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'e14_comunas_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'e14_comunas_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportE14Puestos(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = collect($data['resumenPuestoE14'] ?? [])->values();
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('E14 por puesto');

        $headers = ['Municipio', 'Comuna', 'Puesto', 'Mesas total', 'E14', 'Control', 'Avance %', 'Reconteos', 'Reclamaciones', 'Fotos'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, $row['puesto'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, (int) ($row['mesas_total'] ?? 0));
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, (int) ($row['e14'] ?? 0));
            $sheet->setCellValueByColumnAndRow(6, $rowNumber, (int) ($row['control'] ?? 0));
            $sheet->setCellValueByColumnAndRow(7, $rowNumber, (float) ($row['pct_e14'] ?? 0));
            $sheet->setCellValueByColumnAndRow(8, $rowNumber, (int) ($row['reconteos'] ?? 0));
            $sheet->setCellValueByColumnAndRow(9, $rowNumber, (int) ($row['reclamaciones'] ?? 0));
            $sheet->setCellValueByColumnAndRow(10, $rowNumber, (int) ($row['fotos'] ?? 0));
            $rowNumber++;
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'e14_puestos_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'e14_puestos_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportE14Mesas(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = collect($data['rows'] ?? [])->map(function ($row) {
            return [
                'municipio' => (string) ($row->municipio ?? ''),
                'comuna' => trim((string) ($row->comuna ?? '')) !== '' ? (string) $row->comuna : 'SIN COMUNA',
                'puesto' => (string) ($row->puesto ?? ''),
                'mesa' => (string) ($row->mesa_num ?? ''),
                'coordinador' => (string) ($row->coordinador_nombre ?? 'N/D'),
                'e14' => $row->e14_reportado_at ? 'Sí' : 'No',
                'reconteo' => (int) ($row->reconteo ?? 0) === 1 ? 'Sí' : 'No',
                'reclamacion' => (int) ($row->reclamacion ?? 0) === 1 ? (($row->reclamacion_origen ?? '') === 'nuestra' ? 'Nuestra' : 'Otro candidato') : 'No',
                'jurados_firmaron' => $row->jurados_firmaron ?? '-',
                'control' => $row->control_final_at ? 'Completo' : 'Pendiente',
                'foto' => !empty($row->reclamacion_foto_path) ? 'Sí' : 'No',
            ];
        })->values();
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('E14 por mesa');

        $headers = ['Municipio', 'Comuna', 'Puesto', 'Mesa', 'Coordinador', 'E14', 'Reconteo', 'Reclamacion', 'Jurados firmaron', 'Control', 'Foto'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio']);
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna']);
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, $row['puesto']);
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, $row['mesa']);
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, $row['coordinador']);
            $sheet->setCellValueByColumnAndRow(6, $rowNumber, $row['e14']);
            $sheet->setCellValueByColumnAndRow(7, $rowNumber, $row['reconteo']);
            $sheet->setCellValueByColumnAndRow(8, $rowNumber, $row['reclamacion']);
            $sheet->setCellValueByColumnAndRow(9, $rowNumber, $row['jurados_firmaron']);
            $sheet->setCellValueByColumnAndRow(10, $rowNumber, $row['control']);
            $sheet->setCellValueByColumnAndRow(11, $rowNumber, $row['foto']);
            $rowNumber++;
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'e14_mesas_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'e14_mesas_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportMesasAlertas(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $rows = $this->buildMesaAlertRows(collect($data['rows'] ?? []));
        $eleccion = $data['eleccionOperativa'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Alertas por mesa');

        $headers = [
            'Municipio',
            'Comuna',
            'Puesto',
            'Mesa',
            'Coordinador reporto',
            'E14',
            'Control final',
            'Balance',
            'Alerta balance',
            'Reclamacion',
            'Alerta reclamacion',
            'Reconteo',
            'Alerta reconteo',
            'Jurados firmaron',
            'Alerta jurados',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowNumber, $row['municipio']);
            $sheet->setCellValueByColumnAndRow(2, $rowNumber, $row['comuna']);
            $sheet->setCellValueByColumnAndRow(3, $rowNumber, $row['puesto']);
            $sheet->setCellValueByColumnAndRow(4, $rowNumber, $row['mesa_num']);
            $sheet->setCellValueByColumnAndRow(5, $rowNumber, $row['coordinador_reporto']);
            $sheet->setCellValueByColumnAndRow(6, $rowNumber, $row['e14_estado']);
            $sheet->setCellValueByColumnAndRow(7, $rowNumber, $row['control_estado']);
            $sheet->setCellValueByColumnAndRow(8, $rowNumber, $row['balance_resumen']);
            $sheet->setCellValueByColumnAndRow(9, $rowNumber, $row['balance_alerta']);
            $sheet->setCellValueByColumnAndRow(10, $rowNumber, $row['reclamacion_resumen']);
            $sheet->setCellValueByColumnAndRow(11, $rowNumber, $row['reclamacion_alerta']);
            $sheet->setCellValueByColumnAndRow(12, $rowNumber, $row['reconteo_resumen']);
            $sheet->setCellValueByColumnAndRow(13, $rowNumber, $row['reconteo_alerta']);
            $sheet->setCellValueByColumnAndRow(14, $rowNumber, $row['jurados_firmaron']);
            $sheet->setCellValueByColumnAndRow(15, $rowNumber, $row['jurados_alerta']);
            $rowNumber++;
        }

        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safeName = $eleccion ? preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $eleccion->nombre) : 'eleccion';
        $fileName = 'alertas_mesas_' . trim((string) $safeName, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $tempPath = tempnam(sys_get_temp_dir(), 'alertas_mesas_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }


    private function buildAdminE14DetailRows(int $eleccionId, $detalle)
    {
        if (is_string($detalle)) {
            $decoded = json_decode($detalle, true);
            $detalle = is_array($decoded) ? $decoded : [];
        }

        $detalle = is_array($detalle) ? collect($detalle) : collect();
        $detalleByCandidateId = $detalle->keyBy(function ($item) {
            return (int) ($item['candidato_id'] ?? 0);
        });

        return Candidato::query()
            ->where('eleccion_id', $eleccionId)
            ->where('activo', 1)
            ->whereNotNull('campo_e14')
            ->orderBy('campo_e14')
            ->orderBy('codigo')
            ->get()
            ->map(function ($candidate) use ($detalleByCandidateId) {
                $saved = $detalleByCandidateId->get((int) $candidate->id, []);

                return [
                    'candidato_id' => (int) $candidate->id,
                    'codigo' => (string) ($saved['codigo'] ?? $candidate->codigo ?? '-'),
                    'nombre' => (string) ($saved['nombre'] ?? $candidate->nombre ?? '-'),
                    'votos' => (int) ($saved['votos'] ?? 0),
                ];
            })
            ->values();
    }

    private function buildDashboardData(Request $request): array
    {
        $user = auth()->user();
        $eleccionId = $this->resolveEleccionId((int) $request->get('eleccion_id'));
        $eleccionOperativa = $eleccionId ? Eleccion::find($eleccionId) : null;
        $municipio = trim((string) $request->get('municipio', ''));
        $puestoId = (int) $request->get('puesto_id', 0);
        $coordinadorId = (int) $request->get('coordinador_id', 0);

        $rows = $this->loadMesaReporteRows($eleccionId, $user, [
            'municipio' => $municipio,
            'puesto_id' => $puestoId,
            'coordinador_id' => $coordinadorId,
        ]);

        $visiblePuestoIds = $rows->pluck('puesto_id')->filter()->unique()->values();
        $puestoReportes = collect();

        if (Schema::hasTable('puesto_reportes')) {
            $puestoReportes = PuestoReporte::query()
                ->where('eleccion_id', $eleccionId)
                ->when($visiblePuestoIds->isNotEmpty(), function ($query) use ($visiblePuestoIds) {
                    $query->whereIn('eleccion_puesto_id', $visiblePuestoIds->all());
                }, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->get()
                ->keyBy('eleccion_puesto_id');
        }

        $totalMesas = $rows->count();
        $mesasAfluencia9 = $rows->whereNotNull('afluencia_9')->count();
        $mesasAfluencia11 = $rows->whereNotNull('afluencia_11')->count();
        $mesasAfluencia14 = $rows->whereNotNull('afluencia_14')->count();
        $personasAfluencia9 = (int) $rows->sum(fn ($r) => (int) ($r->afluencia_9 ?? 0));
        $personasAfluencia11 = (int) $rows->sum(fn ($r) => (int) ($r->afluencia_11 ?? 0));
        $personasAfluencia14 = (int) $rows->sum(fn ($r) => (int) ($r->afluencia_14 ?? 0));
        $totalTestigosPresentes = (int) $puestoReportes->sum(fn ($r) => (int) ($r->testigos_presentes ?? 0));
        $mesasAfluencia = $rows->filter(fn ($r) => !is_null($r->afluencia_9) || !is_null($r->afluencia_11) || !is_null($r->afluencia_14))->count();
        $mesasAfluenciaCompleta = $rows->filter(fn ($r) => !is_null($r->afluencia_9) && !is_null($r->afluencia_11) && !is_null($r->afluencia_14))->count();
        $mesasE14 = $rows->whereNotNull('e14_reportado_at')->count();
        $mesasControl = $rows->whereNotNull('control_final_at')->count();
        $mesasReconteo = $rows->where('reconteo', 1)->count();
        $mesasReclamacion = $rows->where('reclamacion', 1)->count();
        $reclamacionesNuestras = $rows->where('reclamacion_origen', 'nuestra')->count();
        $reclamacionesOtros = $rows->where('reclamacion_origen', 'otro')->count();
        $coordinacionesActivas = $this->buildActiveCoordinaciones($eleccionId, $municipio, $puestoId, $user);
        $puestosConCoordinador = $coordinacionesActivas->pluck('puesto_id')->unique()->count();
        $coordinadoresSinActividad = $coordinacionesActivas->filter(fn ($coord) => !$rows->contains('coordinador_id', $coord->abogado_id))->values();

        $resumenMunicipio = $rows->groupBy('municipio')->map(function ($group, $key) {
            $total = $group->count();
            $afluencia = $group->filter(fn ($r) => !is_null($r->afluencia_9) || !is_null($r->afluencia_11) || !is_null($r->afluencia_14))->count();
            $e14 = $group->whereNotNull('e14_reportado_at')->count();
            $control = $group->whereNotNull('control_final_at')->count();
            $personas9 = (int) $group->sum(fn ($r) => (int) ($r->afluencia_9 ?? 0));
            $personas11 = (int) $group->sum(fn ($r) => (int) ($r->afluencia_11 ?? 0));
            $personas14 = (int) $group->sum(fn ($r) => (int) ($r->afluencia_14 ?? 0));
            return [
                'municipio' => $key,
                'mesas_total' => $total,
                'afluencia' => $afluencia,
                'e14' => $e14,
                'control' => $control,
                'reclamaciones' => $group->where('reclamacion', 1)->count(),
                'reconteos' => $group->where('reconteo', 1)->count(),
                'personas_9' => $personas9,
                'personas_11' => $personas11,
                'personas_14' => $personas14,
                'personas_total' => $personas9 + $personas11 + $personas14,
            ];
        })->sortKeys()->values();

        $resumenPuesto = $rows->groupBy('puesto_id')->map(function ($group) {
            $first = $group->first();
            $mesasConReporte = $group->filter(fn ($r) => !is_null($r->afluencia_9) || !is_null($r->afluencia_11) || !is_null($r->afluencia_14))->count();
            $mesasTotal = $group->count();
            $personas9 = (int) $group->sum(fn ($r) => (int) ($r->afluencia_9 ?? 0));
            $personas11 = (int) $group->sum(fn ($r) => (int) ($r->afluencia_11 ?? 0));
            $personas14 = (int) $group->sum(fn ($r) => (int) ($r->afluencia_14 ?? 0));
            return [
                'puesto_id' => $first->puesto_id,
                'municipio' => $first->municipio,
                'comuna' => trim((string) ($first->comuna ?? '')) !== '' ? $first->comuna : 'SIN COMUNA',
                'puesto' => $first->puesto,
                'mesas_total' => $mesasTotal,
                'afluencia' => $mesasConReporte,
                'pct_afluencia' => $mesasTotal > 0 ? round(($mesasConReporte / $mesasTotal) * 100, 1) : 0,
                'personas_9' => $personas9,
                'personas_11' => $personas11,
                'personas_14' => $personas14,
                'personas_total' => $personas9 + $personas11 + $personas14,
                'e14' => $group->whereNotNull('e14_reportado_at')->count(),
                'control' => $group->whereNotNull('control_final_at')->count(),
            ];
        })->sortBy([['municipio', 'asc'], ['comuna', 'asc'], ['puesto', 'asc']])->values();

        $resumenPuestoTestigos = $rows->groupBy('puesto_id')->map(function ($group) use ($puestoReportes) {
            $first = $group->first();
            $puestoReporte = $puestoReportes->get($first->puesto_id);
            return [
                'puesto_id' => $first->puesto_id,
                'municipio' => $first->municipio,
                'comuna' => trim((string) ($first->comuna ?? '')) !== '' ? $first->comuna : 'SIN COMUNA',
                'puesto' => $first->puesto,
                'mesas_total' => $group->count(),
                'testigos_presentes' => (int) ($puestoReporte->testigos_presentes ?? 0),
            ];
        })->sortBy([['municipio', 'asc'], ['comuna', 'asc'], ['puesto', 'asc']])->values();

        $resumenCoordinador = $rows->filter(fn ($r) => !empty($r->coordinador_id))->groupBy('coordinador_id')->map(function ($group) {
            $first = $group->first();
            return [
                'coordinador_id' => $first->coordinador_id,
                'coordinador' => $first->coordinador_nombre,
                'mesas_reportadas' => $group->count(),
                'e14' => $group->whereNotNull('e14_reportado_at')->count(),
                'control' => $group->whereNotNull('control_final_at')->count(),
                'reclamaciones' => $group->where('reclamacion', 1)->count(),
            ];
        })->sortBy('coordinador')->values();

        $resumenComunaAfluencia = $rows
            ->groupBy(function ($row) {
                $municipio = trim((string) ($row->municipio ?? 'N/D'));
                $comuna = trim((string) ($row->comuna ?? 'SIN COMUNA'));
                return $municipio . '||' . ($comuna !== '' ? $comuna : 'SIN COMUNA');
            })
            ->map(function ($group, $key) {
                $first = $group->first();
                $personas9 = (int) $group->sum(fn ($row) => (int) ($row->afluencia_9 ?? 0));
                $personas11 = (int) $group->sum(fn ($row) => (int) ($row->afluencia_11 ?? 0));
                $personas14 = (int) $group->sum(fn ($row) => (int) ($row->afluencia_14 ?? 0));

                return [
                    'municipio' => (string) ($first->municipio ?? 'N/D'),
                    'comuna' => trim((string) ($first->comuna ?? '')) !== '' ? (string) $first->comuna : 'SIN COMUNA',
                    'mesas_total' => $group->count(),
                    'mesas_con_reporte' => $group->filter(fn ($row) => !is_null($row->afluencia_9) || !is_null($row->afluencia_11) || !is_null($row->afluencia_14))->count(),
                    'personas_9' => $personas9,
                    'personas_11' => $personas11,
                    'personas_14' => $personas14,
                    'personas_total' => $personas9 + $personas11 + $personas14,
                ];
            })
            ->sortBy([['municipio', 'asc'], ['comuna', 'asc']])
            ->values();

        $resumenComunaE14 = $rows
            ->groupBy(function ($row) {
                $municipio = trim((string) ($row->municipio ?? 'N/D'));
                $comuna = trim((string) ($row->comuna ?? 'SIN COMUNA'));
                return $municipio . '||' . ($comuna !== '' ? $comuna : 'SIN COMUNA');
            })
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'municipio' => (string) ($first->municipio ?? 'N/D'),
                    'comuna' => trim((string) ($first->comuna ?? '')) !== '' ? (string) $first->comuna : 'SIN COMUNA',
                    'mesas_total' => $group->count(),
                    'e14' => $group->whereNotNull('e14_reportado_at')->count(),
                    'control' => $group->whereNotNull('control_final_at')->count(),
                    'reconteos' => $group->where('reconteo', 1)->count(),
                    'reclamaciones' => $group->where('reclamacion', 1)->count(),
                    'fotos' => $group->whereNotNull('reclamacion_foto_path')->count(),
                ];
            })
            ->sortBy([['municipio', 'asc'], ['comuna', 'asc']])
            ->values();

        $resumenPuestoE14 = $rows->groupBy('puesto_id')->map(function ($group) {
            $first = $group->first();
            $mesasTotal = $group->count();
            $e14 = $group->whereNotNull('e14_reportado_at')->count();
            return [
                'puesto_id' => $first->puesto_id,
                'municipio' => $first->municipio,
                'comuna' => trim((string) ($first->comuna ?? '')) !== '' ? $first->comuna : 'SIN COMUNA',
                'puesto' => $first->puesto,
                'mesas_total' => $mesasTotal,
                'e14' => $e14,
                'control' => $group->whereNotNull('control_final_at')->count(),
                'pct_e14' => $mesasTotal > 0 ? round(($e14 / $mesasTotal) * 100, 1) : 0,
                'reconteos' => $group->where('reconteo', 1)->count(),
                'reclamaciones' => $group->where('reclamacion', 1)->count(),
                'fotos' => $group->whereNotNull('reclamacion_foto_path')->count(),
            ];
        })->sortBy([['municipio', 'asc'], ['comuna', 'asc'], ['puesto', 'asc']])->values();

        $filtros = [
            'municipios' => $rows->pluck('municipio')->filter()->unique()->sort()->values(),
            'puestos' => $rows->groupBy('puesto_id')->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'id' => $first->puesto_id,
                    'puesto' => $first->puesto,
                    'municipio' => $first->municipio,
                ];
            })->sortBy([['municipio', 'asc'], ['puesto', 'asc']])->values(),
            'coordinadores' => $coordinacionesActivas->unique('abogado_id')->sortBy('abogado_nombre')->values(),
        ];

        $rezagos = [
            'sin_afluencia' => $rows->filter(fn ($r) => is_null($r->afluencia_9) && is_null($r->afluencia_11) && is_null($r->afluencia_14))->values(),
            'afluencia_sin_e14' => $rows->filter(fn ($r) => (!is_null($r->afluencia_9) || !is_null($r->afluencia_11) || !is_null($r->afluencia_14)) && is_null($r->e14_reportado_at))->values(),
            'e14_sin_control' => $rows->filter(fn ($r) => !is_null($r->e14_reportado_at) && is_null($r->control_final_at))->values(),
            'puestos_sin_reporte' => $resumenPuesto->filter(fn ($item) => (int) $item['afluencia'] === 0 && (int) $item['e14'] === 0 && (int) $item['control'] === 0)->values(),
            'coordinadores_sin_actividad' => $coordinadoresSinActividad,
        ];

        $chartVillavicencio = $resumenComunaAfluencia
            ->filter(fn ($row) => trim((string) ($row['municipio'] ?? '')) === 'VILLAVICENCIO')
            ->values();

        $chartMunicipios = $resumenMunicipio
            ->filter(fn ($row) => trim((string) ($row['municipio'] ?? '')) !== 'VILLAVICENCIO')
            ->values();

        return [
            'eleccionId' => $eleccionId,
            'eleccionOperativa' => $eleccionOperativa,
            'filtroMunicipio' => $municipio,
            'filtroPuestoId' => $puestoId,
            'filtroCoordinadorId' => $coordinadorId,
            'filtros' => $filtros,
            'resumenMunicipio' => $resumenMunicipio,
            'resumenPuesto' => $resumenPuesto,
            'resumenCoordinador' => $resumenCoordinador,
            'resumenPuestoTestigos' => $resumenPuestoTestigos,
            'rezagos' => $rezagos,
            'rows' => $rows,
            'resumenComunaAfluencia' => $resumenComunaAfluencia,
            'resumenComunaE14' => $resumenComunaE14,
            'resumenPuestoE14' => $resumenPuestoE14,
            'chartVillavicencio' => $chartVillavicencio,
            'chartMunicipios' => $chartMunicipios,
            'flujoConfig' => [
                'afluencia' => $eleccionOperativa ? (bool) $eleccionOperativa->habilitar_afluencia : true,
                'datos_e14' => $eleccionOperativa ? (bool) $eleccionOperativa->habilitar_datos_e14 : true,
                'informacion_final' => $eleccionOperativa ? (bool) $eleccionOperativa->habilitar_informacion_final : true,
                'foto' => $eleccionOperativa ? (bool) $eleccionOperativa->habilitar_foto_e14 : true,
            ],
            'kpis' => [
                'puestos_con_coordinador' => $puestosConCoordinador,
                'total_mesas' => $totalMesas,
                'mesas_afluencia' => $mesasAfluencia,
                'mesas_afluencia_9' => $mesasAfluencia9,
                'mesas_afluencia_11' => $mesasAfluencia11,
                'mesas_afluencia_14' => $mesasAfluencia14,
                'mesas_afluencia_completa' => $mesasAfluenciaCompleta,
                'personas_afluencia_9' => $personasAfluencia9,
                'personas_afluencia_11' => $personasAfluencia11,
                'personas_afluencia_14' => $personasAfluencia14,
                'personas_afluencia_total' => $personasAfluencia9 + $personasAfluencia11 + $personasAfluencia14,
                'total_testigos_presentes' => $totalTestigosPresentes,
                'mesas_e14' => $mesasE14,
                'mesas_control' => $mesasControl,
                'mesas_reconteo' => $mesasReconteo,
                'mesas_reclamacion' => $mesasReclamacion,
                'reclamaciones_nuestras' => $reclamacionesNuestras,
                'reclamaciones_otros' => $reclamacionesOtros,
                'pct_afluencia' => $totalMesas > 0 ? round(($mesasAfluencia / $totalMesas) * 100, 1) : 0,
                'pct_e14' => $totalMesas > 0 ? round(($mesasE14 / $totalMesas) * 100, 1) : 0,
                'pct_control' => $totalMesas > 0 ? round(($mesasControl / $totalMesas) * 100, 1) : 0,
            ],
        ];
    }

    private function buildMesaAlertRows($rows)
    {
        return $this->mapMesaAlertRows($rows);
    }

    private function calculateBalanceSnapshot($row): array
    {
        return $this->mesaBalanceSnapshot($row);
    }

    private function coordinadoresPorPuesto(int $eleccionId, int $puestoId)
    {
        return $this->loadCoordinadoresPorPuesto($eleccionId, $puestoId);
    }

    private function buildActiveCoordinaciones(int $eleccionId, string $municipio = '', int $puestoId = 0, $user = null)
    {
        return DB::table('abogado_coordinaciones as ac')
            ->join('abogados as a', 'a.id', '=', 'ac.abogado_id')
            ->leftJoin('eleccion_mesas as em', 'em.id', '=', 'ac.eleccion_mesa_id')
            ->leftJoin('eleccion_puestos as ep_cod', function ($join) {
                $join->on('ep_cod.eleccion_id', '=', 'ac.eleccion_id')
                    ->where(function ($query) {
                        $query->whereColumn('ep_cod.codigo_puesto', 'ac.codpuesto')
                            ->orWhereRaw('CONCAT(ep_cod.dd, ep_cod.mm, ep_cod.zz, ep_cod.pp) = ac.codpuesto')
                            ->orWhere(function ($shortCode) {
                                $shortCode->whereRaw('CHAR_LENGTH(ac.codpuesto) <= 2')
                                    ->whereColumn('ep_cod.pp', 'ac.codpuesto');
                            });
                    });
            })
            ->leftJoin('eleccion_puestos as ep_mesa', 'ep_mesa.id', '=', 'em.eleccion_puesto_id')
            ->where('ac.eleccion_id', $eleccionId)
            ->whereNull('ac.released_at')
            ->orderBy('a.nombre')
            ->get([
                'ac.id', 'a.id as abogado_id', 'a.nombre as abogado_nombre',
                DB::raw('COALESCE(ep_mesa.id, ep_cod.id) as puesto_id'),
                DB::raw('COALESCE(ep_mesa.municipio, ep_cod.municipio) as municipio'),
                DB::raw('COALESCE(ep_mesa.puesto, ep_cod.puesto) as puesto'),
                DB::raw('COALESCE(ep_mesa.mm, ep_cod.mm) as mm'),
            ])
            ->filter(function ($row) use ($municipio, $puestoId, $user) {
                if (empty($row->puesto_id)) {
                    return false;
                }
                if ($municipio !== '' && $row->municipio !== $municipio) {
                    return false;
                }
                if ($puestoId > 0 && (int) $row->puesto_id !== $puestoId) {
                    return false;
                }
                if (!$user || (int) $user->id === 1 || (int) $user->role === 1) {
                    return true;
                }
                $municipios = $this->splitCsv($user->mun);
                if (!empty($municipios)) {
                    return in_array((string) $row->mm, $municipios, true);
                }
                return true;
            })
            ->values();
    }
}
