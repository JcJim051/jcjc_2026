<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use App\Traits\EleccionScope;
use Illuminate\Support\Facades\DB;

class ConsultorController extends Controller
{
    use EleccionScope;

    private array $mesaOkEstados = ['asignado', 'validado', 'postulado', 'acreditado'];

    public function index()
    {
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
        $metrics = $this->buildMetrics($eleccionId);
        $eleccionOperativa = $eleccionId ? Eleccion::find($eleccionId) : null;

        return view('admin.consultors.index', array_merge($metrics, [
            'eleccionId' => $eleccionId,
            'eleccionOperativa' => $eleccionOperativa,
        ]));
    }

    public function getData()
    {
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
        $metrics = $this->buildMetrics($eleccionId);

        return response()->json([
            'dat' => $metrics['dat'],
            'lablemun' => $metrics['lablemun'],
            'okaniv' => $metrics['okaniv'],
            'nookaniv' => $metrics['nookaniv'],
            'okanim' => $metrics['okanim'],
            'nookanim' => $metrics['nookanim'],
            'remokd' => $metrics['remokd'],
            'remnookd' => $metrics['remnookd'],
            'remokv' => $metrics['remokv'],
            'remnookv' => $metrics['remnookv'],
            'remokm' => $metrics['remokm'],
            'remnookm' => $metrics['remnookm'],
            'puestosd' => $metrics['puestosd'],
            'puestosv' => $metrics['puestosv'],
            'puestosm' => $metrics['puestosm'],
            'okd' => $metrics['okd'],
            'nookd' => $metrics['nookd'],
            'nookv' => $metrics['nookv'],
            'okv' => $metrics['okv'],
            'nookm' => $metrics['nookm'],
            'okm' => $metrics['okm'],
        ]);
    }

    private function buildMetrics(?int $eleccionId): array
    {
        $empty = collect();
        if (!$eleccionId) {
            return $this->emptyMetrics($empty);
        }

        $base = $this->baseMesasQuery($eleccionId);

        $okd = $this->countOk(clone $base);
        $totald = (clone $base)->count();
        $nookd = max(0, $totald - $okd);

        $villaBase = (clone $base)->where('ep.mm', '001');
        $okv = $this->countOk(clone $villaBase);
        $totalv = (clone $villaBase)->count();
        $nookv = max(0, $totalv - $okv);

        $munBase = (clone $base)->where('ep.mm', '<>', '001');
        $okm = $this->countOk(clone $munBase);
        $totalm = (clone $munBase)->count();
        $nookm = max(0, $totalm - $okm);

        $dat = (clone $base)
            ->where('ep.mm', '001')
            ->select('ep.zz as codzon')
            ->selectRaw($this->okSumSql() . ' as T')
            ->selectRaw($this->pendingSumSql() . ' as F')
            ->groupBy('ep.zz')
            ->orderBy('ep.zz')
            ->get();

        $data = $dat;
        $not = $dat;

        $lablemun = (clone $base)
            ->where('ep.mm', '<>', '001')
            ->select('ep.municipio')
            ->selectRaw($this->okSumSql() . ' as T')
            ->selectRaw($this->pendingSumSql() . ' as F')
            ->groupBy('ep.municipio')
            ->orderBy('ep.municipio')
            ->get();

        $okmun = $lablemun;
        $nookmun = $lablemun;

        $okaniv = $this->countOk(clone $villaBase);
        $nookaniv = max(0, $totalv - $okaniv);
        $okanim = $this->countOk(clone $munBase);
        $nookanim = max(0, $totalm - $okanim);

        $puestosBase = $this->basePuestosQuery($eleccionId);
        $puestosd = (clone $puestosBase)->distinct('ep.id')->count('ep.id');
        $puestosv = (clone $puestosBase)->where('ep.mm', '001')->distinct('ep.id')->count('ep.id');
        $puestosm = (clone $puestosBase)->where('ep.mm', '<>', '001')->distinct('ep.id')->count('ep.id');

        $remanentesStats = $this->buildRemanentesStats($eleccionId);
        $remokd = $remanentesStats['totals']['ok'];
        $remnookd = $remanentesStats['totals']['faltan'];
        $remokv = $remanentesStats['villa']['ok'];
        $remnookv = $remanentesStats['villa']['faltan'];
        $remokm = $remanentesStats['municipios']['ok'];
        $remnookm = $remanentesStats['municipios']['faltan'];

        $avancePuestos = $this->baseMesasQuery($eleccionId, true)
            ->selectRaw("CONCAT(ep.dd, ep.mm, ep.zz, ep.pp) as codcor")
            ->addSelect('ep.municipio', 'ep.puesto')
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.codigo = 101 THEN em.id END) as mesas_101')
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.codigo = 103 THEN em.id END) as mesas_103')
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.codigo = 4 THEN em.id END) as mesas_04')
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.codigo = 101 AND (ra.status_ok = 1 OR ca.status_ok = 1) THEN em.id END) as ok_101')
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.codigo = 103 AND (ra.status_ok = 1 OR ca.status_ok = 1) THEN em.id END) as ok_103')
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.codigo = 4 AND (ra.status_ok = 1 OR ca.status_ok = 1) THEN em.id END) as ok_04')
            ->selectRaw('COUNT(DISTINCT em.id) as total_mesas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ra.status_ok = 1 OR ca.status_ok = 1 THEN em.id END) as total_ok')
            ->selectRaw('0 as rem')
            ->selectRaw('0 as rem_ok')
            ->groupBy('ep.dd', 'ep.mm', 'ep.zz', 'ep.pp', 'ep.municipio', 'ep.puesto')
            ->orderBy('ep.dd')
            ->orderBy('ep.mm')
            ->orderBy('ep.zz')
            ->orderBy('ep.pp')
            ->get()
            ->map(function ($row) use ($remanentesStats) {
                $rem = $remanentesStats['puestos'][$row->codcor] ?? ['total' => 0, 'ok' => 0];
                $row->rem = (int) $rem['total'];
                $row->rem_ok = (int) $rem['ok'];
                return $row;
            });

        $mesasok = $avancePuestos;

        $avanceCandidatos = $this->baseMesasQuery($eleccionId, true)
            ->whereIn('c.codigo', [101, 4, 103])
            ->select('c.codigo as candidato')
            ->selectRaw('COUNT(DISTINCT em.id) as total_mesas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ra.status_ok = 1 OR ca.status_ok = 1 THEN em.id END) as mesas_ok')
            ->groupBy('c.codigo')
            ->get()
            ->keyBy('candidato');

        return compact(
            'data',
            'dat',
            'not',
            'lablemun',
            'okmun',
            'nookmun',
            'okaniv',
            'nookaniv',
            'okanim',
            'nookanim',
            'puestosd',
            'puestosv',
            'puestosm',
            'mesasok',
            'avancePuestos',
            'avanceCandidatos',
            'okd',
            'nookd',
            'okv',
            'nookv',
            'okm',
            'nookm',
            'remokd',
            'remnookd',
            'remokv',
            'remnookv',
            'remokm',
            'remnookm'
        );
    }

    private function baseMesasQuery(int $eleccionId, bool $withCandidates = false)
    {
        $user = auth()->user();
        $referidoAgg = DB::table('referidos')
            ->where('eleccion_id', $eleccionId)
            ->whereNotNull('mesa_num')
            ->where('mesa_num', '>', 0)
            ->select('eleccion_puesto_id', 'mesa_num')
            ->selectRaw("MAX(CASE WHEN estado IN ('asignado', 'validado', 'postulado', 'acreditado') THEN 1 ELSE 0 END) as status_ok")
            ->selectRaw("COALESCE(MAX(CASE WHEN estado <> 'rechazado' THEN id END), MAX(id)) as best_referido_id")
            ->groupBy('eleccion_puesto_id', 'mesa_num');

        $coordinacionAgg = DB::table('abogado_coordinaciones as ac')
            ->where('ac.eleccion_id', $eleccionId)
            ->whereNull('ac.released_at')
            ->whereNotNull('ac.eleccion_mesa_id')
            ->select('ac.eleccion_mesa_id')
            ->selectRaw("MAX(CASE WHEN ac.validacion_estado IN ('asignado', 'validado') THEN 1 ELSE 0 END) as status_ok")
            ->selectRaw("MAX(CASE WHEN ac.validacion_estado IN ('asignado', 'validado') THEN 1 ELSE 0 END) as occupied")
            ->groupBy('ac.eleccion_mesa_id');

        $query = DB::table('eleccion_mesas as em')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'em.eleccion_puesto_id')
            ->leftJoinSub($referidoAgg, 'ra', function ($join) {
                $join->on('ra.eleccion_puesto_id', '=', 'em.eleccion_puesto_id')
                    ->on('ra.mesa_num', '=', 'em.mesa_num');
            })
            ->leftJoinSub($coordinacionAgg, 'ca', function ($join) {
                $join->on('ca.eleccion_mesa_id', '=', 'em.id');
            })
            ->leftJoin('referidos as r', 'r.id', '=', 'ra.best_referido_id')
            ->where('em.eleccion_id', $eleccionId);

        if ($withCandidates) {
            $query->leftJoin('eleccion_mesa_candidatos as emc', 'emc.eleccion_mesa_id', '=', 'em.id')
                ->leftJoin('candidatos as c', 'c.id', '=', 'emc.candidato_id');
        }

        $this->applyEleccionScope($query, $eleccionId, 'ep');
        if ($user) {
            $this->applyUserGeoScope($query, $user, 'ep');
            $this->applyCandidateScope($query, $user, 'em', $eleccionId);
        }

        return $query;
    }

    private function basePuestosQuery(int $eleccionId)
    {
        $user = auth()->user();
        $query = DB::table('eleccion_puestos as ep')
            ->where('ep.eleccion_id', $eleccionId);

        $this->applyEleccionScope($query, $eleccionId, 'ep');
        if ($user) {
            $this->applyUserGeoScope($query, $user, 'ep');
        }

        return $query;
    }

    private function buildRemanentesStats(int $eleccionId): array
    {
        $puestos = $this->basePuestosQuery($eleccionId)
            ->select([
                'ep.id',
                'ep.dd',
                'ep.mm',
                'ep.zz',
                'ep.pp',
                'ep.codigo_puesto',
                'ep.mesas_total',
            ])
            ->get();

        $coordinaciones = DB::table('abogado_coordinaciones')
            ->where('eleccion_id', $eleccionId)
            ->whereNull('released_at')
            ->whereNull('eleccion_mesa_id')
            ->select('codpuesto')
            ->selectRaw('COUNT(*) as ocupados')
            ->selectRaw("SUM(CASE WHEN validacion_estado IN ('asignado', 'validado') THEN 1 ELSE 0 END) as ok")
            ->groupBy('codpuesto')
            ->get()
            ->keyBy('codpuesto');

        $stats = [
            'puestos' => [],
            'totals' => ['total' => 0, 'ok' => 0, 'faltan' => 0],
            'villa' => ['total' => 0, 'ok' => 0, 'faltan' => 0],
            'municipios' => ['total' => 0, 'ok' => 0, 'faltan' => 0],
        ];

        foreach ($puestos as $puesto) {
            $totalRem = $this->remanentesPermitidosPorPuesto((int) ($puesto->mesas_total ?? 0));
            $codcor = $this->puestoCodcor($puesto);
            $coord = null;
            foreach ($this->puestoCoordinacionKeys($puesto) as $key) {
                if ($coordinaciones->has($key)) {
                    $coord = $coordinaciones->get($key);
                    break;
                }
            }
            $ok = min($totalRem, (int) ($coord->ok ?? 0));
            $faltan = max(0, $totalRem - $ok);

            $stats['puestos'][$codcor] = [
                'total' => $totalRem,
                'ok' => $ok,
                'faltan' => $faltan,
            ];

            $bucket = $puesto->mm === '001' ? 'villa' : 'municipios';
            foreach (['totals', $bucket] as $key) {
                $stats[$key]['total'] += $totalRem;
                $stats[$key]['ok'] += $ok;
                $stats[$key]['faltan'] += $faltan;
            }
        }

        return $stats;
    }

    private function remanentesPermitidosPorPuesto(int $totalMesas): int
    {
        if ($totalMesas <= 0) {
            return 0;
        }

        return $totalMesas < 10 ? 1 : (int) ceil($totalMesas * 0.10);
    }

    private function puestoCodcor(object $puesto): string
    {
        return (string) ($puesto->dd ?? '') . (string) ($puesto->mm ?? '') . (string) ($puesto->zz ?? '') . (string) ($puesto->pp ?? '');
    }

    private function puestoCoordinacionKeys(object $puesto): array
    {
        $codigo = trim((string) ($puesto->codigo_puesto ?? ''));
        return array_values(array_unique(array_filter([
            $codigo,
            $this->puestoCodcor($puesto),
            trim((string) ($puesto->pp ?? '')),
        ])));
    }

    private function countOk($query): int
    {
        return (int) $query
            ->where(function ($q) {
                $q->where('ra.status_ok', 1)
                    ->orWhere('ca.status_ok', 1);
            })
            ->count();
    }

    private function okSumSql(): string
    {
        return 'SUM(CASE WHEN ra.status_ok = 1 OR ca.status_ok = 1 THEN 1 ELSE 0 END)';
    }

    private function pendingSumSql(): string
    {
        return 'SUM(CASE WHEN ra.status_ok = 1 OR ca.status_ok = 1 THEN 0 ELSE 1 END)';
    }

    private function emptyMetrics($empty): array
    {
        return [
            'data' => $empty,
            'dat' => $empty,
            'not' => $empty,
            'lablemun' => $empty,
            'okmun' => $empty,
            'nookmun' => $empty,
            'okaniv' => 0,
            'nookaniv' => 0,
            'okanim' => 0,
            'nookanim' => 0,
            'remokd' => 0,
            'remnookd' => 0,
            'remokv' => 0,
            'remnookv' => 0,
            'remokm' => 0,
            'remnookm' => 0,
            'puestosd' => 0,
            'puestosv' => 0,
            'puestosm' => 0,
            'mesasok' => $empty,
            'avancePuestos' => $empty,
            'avanceCandidatos' => $empty,
            'okd' => 0,
            'nookd' => 0,
            'okv' => 0,
            'nookv' => 0,
            'okm' => 0,
            'nookm' => 0,
        ];
    }
}
