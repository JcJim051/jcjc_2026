<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EleccionPuesto;
use App\Models\Referido;
use App\Traits\EleccionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileModulesController extends Controller
{
    use EleccionScope;

    public function asistencia(Request $request): JsonResponse
    {
        $eleccionId = $this->resolveEleccionId((int) $request->query('eleccion_id'));
        $user = $request->user();

        $base = DB::table('sellers');
        $this->applyEleccionScopeSeller($base, $eleccionId, 'sellers');
        $this->applyMobileUserSellerScope($base, $user);

        $testigosBase = (clone $base)->where('mesa', '<>', 'Rem');
        $remanentesBase = (clone $base)->where('mesa', '=', 'Rem');

        $summary = [
            'total_testigos' => (clone $testigosBase)->count(),
            'testigos_reportados' => (clone $testigosBase)->where('statusasistencia', 1)->count(),
            'total_remanentes' => (clone $remanentesBase)->count(),
            'remanentes_reportados' => (clone $remanentesBase)->where('statusasistencia', 1)->count(),
        ];

        $items = (clone $base)
            ->select(['id', 'municipio', 'puesto', 'mesa', 'statusasistencia'])
            ->orderBy('municipio')
            ->orderBy('puesto')
            ->orderByRaw("CASE WHEN mesa = 'Rem' THEN 1 ELSE 0 END")
            ->orderBy('mesa')
            ->limit(250)
            ->get()
            ->map(function ($row) {
                $isRem = strtoupper((string) $row->mesa) === 'REM';

                return [
                    'id' => (int) $row->id,
                    'municipio' => (string) $row->municipio,
                    'puesto' => (string) $row->puesto,
                    'mesa' => (string) $row->mesa,
                    'tipo' => $isRem ? 'remanente' : 'testigo',
                    'estado' => ((int) $row->statusasistencia === 1) ? 'reportada' : 'pendiente',
                ];
            })
            ->values();

        return response()->json([
            'summary' => $summary,
            'items' => $items,
        ]);
    }

    public function ani(Request $request): JsonResponse
    {
        $eleccionId = $this->resolveEleccionId((int) $request->query('eleccion_id'));
        if (!$eleccionId) {
            return response()->json([
                'summary' => [
                    'total' => 0,
                    'pendiente' => 0,
                    'asignado' => 0,
                    'validado' => 0,
                ],
                'items' => [],
            ]);
        }

        $user = $request->user();

        $base = Referido::query()
            ->from('referidos as r')
            ->leftJoin('personas as p', 'p.id', '=', 'r.persona_id')
            ->leftJoin('eleccion_puestos as ep', 'ep.id', '=', 'r.eleccion_puesto_id')
            ->where('r.eleccion_id', $eleccionId)
            ->select([
                'r.id',
                'r.estado',
                'r.mesa_num',
                'r.observaciones',
                'r.updated_at',
                'p.nombre',
                'p.cedula',
                'ep.municipio',
                'ep.puesto',
                'ep.dd',
                'ep.mm',
                'ep.zz',
                'ep.pp',
            ]);

        $this->applyEleccionScope($base, $eleccionId, 'ep');
        $this->applyUserGeoScope($base, $user, 'ep');

        $summaryRows = (clone $base)
            ->select('r.estado', DB::raw('COUNT(*) as total'))
            ->groupBy('r.estado')
            ->get();

        $summary = [
            'total' => (int) $summaryRows->sum('total'),
            'pendiente' => 0,
            'asignado' => 0,
            'validado' => 0,
        ];

        foreach ($summaryRows as $row) {
            $estado = (string) $row->estado;
            $count = (int) $row->total;
            if ($estado === 'asignado') {
                $summary['asignado'] += $count;
            } elseif ($estado === 'validado') {
                $summary['validado'] += $count;
            } else {
                $summary['pendiente'] += $count;
            }
        }

        $items = (clone $base)
            ->orderByDesc('r.updated_at')
            ->limit(250)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'estado' => (string) $row->estado,
                    'nombre' => (string) ($row->nombre ?? ''),
                    'cedula' => (string) ($row->cedula ?? ''),
                    'municipio' => (string) ($row->municipio ?? ''),
                    'puesto' => (string) ($row->puesto ?? ''),
                    'mesa_num' => (int) ($row->mesa_num ?? 0),
                    'observaciones' => (string) ($row->observaciones ?? ''),
                    'updated_at' => (string) ($row->updated_at ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'summary' => $summary,
            'items' => $items,
        ]);
    }

    public function transmision(Request $request): JsonResponse
    {
        $eleccionId = $this->resolveEleccionId((int) $request->query('eleccion_id'));
        $user = $request->user();

        $base = DB::table('sellers');
        $this->applyEleccionScopeSeller($base, $eleccionId, 'sellers');
        $this->applyMobileUserSellerScope($base, $user);

        $summary = [
            'total_mesas' => (clone $base)->count(),
            'mesas_transmitidas' => (clone $base)->whereNotNull('gob1')->count(),
            'fotos_completas' => (clone $base)->whereNotNull('e14')->whereNotNull('e14_2')->count(),
            'reclamaciones' => (clone $base)->where('reclamacion', 1)->count(),
            'reclamaciones_con_foto' => (clone $base)->where('reclamacion', 1)->whereNotNull('fotorec')->count(),
        ];

        $items = (clone $base)
            ->select([
                'municipio',
                DB::raw('COUNT(*) as total_mesas'),
                DB::raw('SUM(CASE WHEN gob1 IS NOT NULL THEN 1 ELSE 0 END) as mesas_transmitidas'),
                DB::raw('SUM(CASE WHEN e14 IS NOT NULL AND e14_2 IS NOT NULL THEN 1 ELSE 0 END) as fotos_completas'),
            ])
            ->groupBy('municipio')
            ->orderByDesc('mesas_transmitidas')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $total = max((int) $row->total_mesas, 1);
                $transmitidas = (int) $row->mesas_transmitidas;

                return [
                    'municipio' => (string) $row->municipio,
                    'total_mesas' => (int) $row->total_mesas,
                    'mesas_transmitidas' => $transmitidas,
                    'fotos_completas' => (int) $row->fotos_completas,
                    'avance_porcentaje' => (int) round(($transmitidas / $total) * 100),
                ];
            })
            ->values();

        return response()->json([
            'summary' => $summary,
            'items' => $items,
        ]);
    }

    private function applyMobileUserSellerScope($query, $user): void
    {
        if (!$user || (int) $user->id === 1) {
            return;
        }

        $puestos = $this->splitCsv($user->codpuesto);
        if (!empty($puestos)) {
            $query->whereIn('codcor', $puestos);
            return;
        }

        $municipios = $this->normalizeMmList($this->splitCsv($user->mun));
        if (!empty($municipios)) {
            $query->whereIn('codmun', $municipios);
            return;
        }

        $zonas = $this->splitCsv($user->codzon);
        if (!empty($zonas)) {
            $query->whereIn('codescru', $zonas);
        }
    }
}
