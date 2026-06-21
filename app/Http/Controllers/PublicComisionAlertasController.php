<?php

namespace App\Http\Controllers;

use App\Models\ComisionMesaComentario;
use App\Models\Candidato;
use App\Models\Eleccion;
use App\Models\EleccionPuesto;
use App\Models\TerritorioToken;
use App\Traits\BuildsMesaAlertas;
use Illuminate\Http\Request;

class PublicComisionAlertasController extends Controller
{
    use BuildsMesaAlertas;

    public function index(Request $request, string $token)
    {
        $tokenRow = $this->getComisionTokenOrFail($token, false);

        if (!$this->tokenDisponible($tokenRow)) {
            return view('public.comision_alertas.cerrado', ['token' => $tokenRow]);
        }

        $zonas = $this->parseZonasToken($tokenRow->zz);

        $rows = $this->loadMesaReporteRows($tokenRow->eleccion_id, null, [
            'dd' => $tokenRow->dd,
            'mm' => $tokenRow->mm,
            'zz_list' => $zonas,
            'apply_eleccion_scope' => false,
        ]);
        $alertRows = $this->mapMesaAlertRows($rows);
        $territorio = $this->resolveTerritorio($tokenRow);
        $zonasLabel = $this->formatZonasLabel($zonas);
        $puestoId = (int) $request->get('puesto_id');

        $puestos = $alertRows
            ->groupBy('puesto_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'puesto_id' => (int) $first['puesto_id'],
                    'puesto' => $first['puesto'],
                    'comuna' => $first['comuna'],
                    'mesas_total' => $group->count(),
                    'mesas_e14' => $group->where('e14_estado', 'Sí')->count(),
                    'mesas_control' => $group->where('control_estado', 'Completo')->count(),
                    'alertas_balance' => $group->where('balance_alerta', 'Alerta')->count(),
                    'alertas_reclamacion' => $group->where('reclamacion_alerta', 'Alerta')->count(),
                    'alertas_reconteo' => $group->where('reconteo_alerta', 'Alerta')->count(),
                    'alertas_jurados' => $group->where('jurados_alerta', 'Alerta')->count(),
                ];
            })
            ->sortBy([['comuna', 'asc'], ['puesto', 'asc']])
            ->values();

        $selectedRows = $puestoId > 0
            ? $alertRows->where('puesto_id', $puestoId)->values()
            : collect();

        return view('public.comision_alertas.index', [
            'token' => $tokenRow,
            'eleccion' => Eleccion::find($tokenRow->eleccion_id),
            'territorio' => $territorio,
            'alertRows' => $selectedRows,
            'puestos' => $puestos,
            'puestoSeleccionadoId' => $puestoId,
            'puestoSeleccionado' => $puestos->firstWhere('puesto_id', $puestoId),
            'zonasLabel' => $zonasLabel,
            'kpis' => [
                'puestos_total' => $rows->pluck('puesto_id')->filter()->unique()->count(),
                'mesas_total' => $rows->count(),
                'mesas_e14' => $rows->whereNotNull('e14_reportado_at')->count(),
                'mesas_control' => $rows->whereNotNull('control_final_at')->count(),
                'alertas_balance' => $alertRows->where('balance_alerta', 'Alerta')->count(),
                'reclamaciones' => $rows->where('reclamacion', 1)->count(),
                'reconteos' => $rows->where('reconteo', 1)->count(),
                'jurados_alerta' => $alertRows->where('jurados_alerta', 'Alerta')->count(),
            ],
        ]);
    }

    public function show(string $token, int $mesa)
    {
        $tokenRow = $this->getComisionTokenOrFail($token, false);

        if (!$this->tokenDisponible($tokenRow)) {
            return view('public.comision_alertas.cerrado', ['token' => $tokenRow]);
        }

        $zonas = $this->parseZonasToken($tokenRow->zz);

        $rows = $this->loadMesaReporteRows($tokenRow->eleccion_id, null, [
            'dd' => $tokenRow->dd,
            'mm' => $tokenRow->mm,
            'zz_list' => $zonas,
            'mesa_id' => $mesa,
            'apply_eleccion_scope' => false,
        ]);
        $mesaRow = $this->mapMesaAlertRows($rows)->first();
        abort_unless($mesaRow, 404);

        $row = $mesaRow['row'];
        $coordinadores = $this->loadCoordinadoresPorPuesto($tokenRow->eleccion_id, (int) $mesaRow['puesto_id']);
        $reclamacionCandidato = null;
        if (!empty($row->reclamacion_candidato_id)) {
            $candidate = \App\Models\Candidato::find((int) $row->reclamacion_candidato_id);
            if ($candidate) {
                $reclamacionCandidato = trim(($candidate->codigo ? $candidate->codigo . ' - ' : '') . $candidate->nombre);
            }
        }

        $comentarios = ComisionMesaComentario::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('eleccion_mesa_id', $mesa)
            ->latest()
            ->get();

        return view('public.comision_alertas.show', [
            'token' => $tokenRow,
            'eleccion' => Eleccion::find($tokenRow->eleccion_id),
            'territorio' => $this->resolveTerritorio($tokenRow),
            'zonasLabel' => $this->formatZonasLabel($zonas),
            'mesa' => $mesaRow,
            'e14DetalleRows' => $this->buildE14DetailRows((int) $tokenRow->eleccion_id, $row->e14_detalle),
            'coordinadores' => $coordinadores,
            'reclamacionCandidato' => $reclamacionCandidato,
            'comentarios' => $comentarios,
        ]);
    }

    public function storeComentario(Request $request, string $token, int $mesa)
    {
        $tokenRow = $this->getComisionTokenOrFail($token);

        $zonas = $this->parseZonasToken($tokenRow->zz);

        $rows = $this->loadMesaReporteRows($tokenRow->eleccion_id, null, [
            'dd' => $tokenRow->dd,
            'mm' => $tokenRow->mm,
            'zz_list' => $zonas,
            'mesa_id' => $mesa,
            'apply_eleccion_scope' => false,
        ]);
        abort_unless($rows->isNotEmpty(), 404);

        $data = $request->validate([
            'autor_nombre' => ['required', 'string', 'max:255'],
            'comentario' => ['required', 'string', 'max:5000'],
        ]);

        ComisionMesaComentario::create([
            'eleccion_id' => $tokenRow->eleccion_id,
            'eleccion_mesa_id' => $mesa,
            'territorio_token_id' => $tokenRow->id,
            'autor_nombre' => trim((string) $data['autor_nombre']),
            'comentario' => trim((string) $data['comentario']),
        ]);

        return back()->with('success', 'Observación guardada correctamente.');
    }

    private function getComisionTokenOrFail(string $token, bool $requireActive = true): TerritorioToken
    {
        $tokenRow = TerritorioToken::query()
            ->where('token', $token)
            ->first();

        if (!$tokenRow) {
            abort(404);
        }

        if (($tokenRow->modulo_resuelto ?? null) !== 'comision_alertas') {
            abort(404);
        }

        if ($requireActive && !$tokenRow->activo) {
            abort(404);
        }

        if ($requireActive && $tokenRow->expires_at && $tokenRow->expires_at->isPast()) {
            abort(404);
        }

        return $tokenRow;
    }

    private function tokenDisponible(TerritorioToken $tokenRow): bool
    {
        if (!$tokenRow->activo) {
            return false;
        }

        if ($tokenRow->expires_at && $tokenRow->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    private function resolveTerritorio(TerritorioToken $tokenRow): ?EleccionPuesto
    {
        return EleccionPuesto::query()
            ->where('eleccion_id', $tokenRow->eleccion_id)
            ->where('dd', $tokenRow->dd)
            ->where('mm', $tokenRow->mm)
            ->select('departamento', 'municipio')
            ->first();
    }
    private function parseZonasToken(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->map(fn ($item) => str_pad($item, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->values()
            ->all();
    }

    private function formatZonasLabel(array $zonas): string
    {
        return empty($zonas) ? 'Zona N/D' : 'Zonas ' . implode(', ', $zonas);
    }

    private function buildE14DetailRows(int $eleccionId, $detalle)
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
}
