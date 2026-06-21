<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait BuildsMesaAlertas
{
    protected function loadMesaReporteRows(int $eleccionId, $user = null, array $filters = []): Collection
    {
        $municipio = trim((string) ($filters['municipio'] ?? ''));
        $puestoId = (int) ($filters['puesto_id'] ?? 0);
        $coordinadorId = (int) ($filters['coordinador_id'] ?? 0);
        $mesaId = (int) ($filters['mesa_id'] ?? 0);
        $dd = trim((string) ($filters['dd'] ?? ''));
        $mm = trim((string) ($filters['mm'] ?? ''));
        $zz = trim((string) ($filters['zz'] ?? ''));
        $zzList = collect($filters['zz_list'] ?? [])
            ->map(fn ($item) => str_pad(trim((string) $item), 2, '0', STR_PAD_LEFT))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $applyEleccionScope = array_key_exists('apply_eleccion_scope', $filters)
            ? (bool) $filters['apply_eleccion_scope']
            : true;

        $base = DB::table('eleccion_mesas as em')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'em.eleccion_puesto_id')
            ->leftJoin('mesa_reportes as mr', 'mr.eleccion_mesa_id', '=', 'em.id')
            ->leftJoin('abogados as a', 'a.id', '=', 'mr.abogado_id')
            ->where('em.eleccion_id', $eleccionId);

        if ($applyEleccionScope) {
            $this->applyEleccionScope($base, $eleccionId, 'ep');
        }

        if ($user) {
            $this->applyUserGeoScope($base, $user, 'ep');
            $this->applyCandidateScope($base, $user, 'em', $eleccionId);
        }

        if ($municipio !== '') {
            $base->where('ep.municipio', $municipio);
        }
        if ($puestoId > 0) {
            $base->where('ep.id', $puestoId);
        }
        if ($coordinadorId > 0) {
            $base->where('mr.abogado_id', $coordinadorId);
        }
        if ($mesaId > 0) {
            $base->where('em.id', $mesaId);
        }
        if ($dd !== '') {
            $base->where('ep.dd', str_pad($dd, 2, '0', STR_PAD_LEFT));
        }
        if ($mm !== '') {
            $base->where('ep.mm', str_pad($mm, 3, '0', STR_PAD_LEFT));
        }
        if (!empty($zzList)) {
            $base->whereIn('ep.zz', $zzList);
        } elseif ($zz !== '') {
            $base->where('ep.zz', str_pad($zz, 2, '0', STR_PAD_LEFT));
        }

        return $base->orderBy('ep.municipio')
            ->orderBy('ep.zz')
            ->orderBy('ep.puesto')
            ->orderBy('em.mesa_num')
            ->get([
                'em.id as mesa_id',
                'em.mesa_num',
                'ep.id as puesto_id',
                'ep.dd',
                'ep.mm',
                'ep.zz',
                'ep.puesto',
                'ep.municipio',
                'ep.comuna',
                'mr.id as reporte_id',
                'mr.afluencia_9',
                'mr.afluencia_11',
                'mr.afluencia_14',
                'mr.e14_reportado_at',
                'mr.control_final_at',
                'mr.reconteo',
                'mr.reclamacion',
                'mr.reclamacion_origen',
                'mr.reclamacion_foto_path',
                'mr.jurados_firmaron',
                'mr.e14_foto_path',
                'mr.e14_detalle',
                'mr.censo_mesa',
                'mr.votos_en_urna',
                'mr.votos_incinerados',
                'mr.votos_blanco',
                'mr.votos_nulos',
                'mr.votos_no_marcados',
                'mr.reclamacion_candidato_id',
                'mr.reclamacion_comentario',
                'a.nombre as coordinador_nombre',
                'mr.abogado_id as coordinador_id',
            ]);
    }

    protected function mapMesaAlertRows($rows): Collection
    {
        return collect($rows)->map(function ($row) {
            $balance = $this->mesaBalanceSnapshot($row);
            $hasReclamacion = (int) ($row->reclamacion ?? 0) === 1;
            $hasReconteo = (int) ($row->reconteo ?? 0) === 1;
            $jurados = $row->jurados_firmaron;
            $juradosAlerta = is_null($jurados)
                ? 'Pendiente'
                : ((int) $jurados < 6 ? 'Alerta' : 'OK');

            return [
                'mesa_id' => (int) $row->mesa_id,
                'puesto_id' => (int) $row->puesto_id,
                'dd' => (string) ($row->dd ?? ''),
                'mm' => (string) ($row->mm ?? ''),
                'zz' => (string) ($row->zz ?? ''),
                'municipio' => (string) ($row->municipio ?? ''),
                'comuna' => trim((string) ($row->comuna ?? '')) !== '' ? (string) $row->comuna : 'SIN COMUNA',
                'puesto' => (string) ($row->puesto ?? ''),
                'mesa_num' => (string) ($row->mesa_num ?? ''),
                'coordinador_reporto' => (string) ($row->coordinador_nombre ?: 'N/D'),
                'coordinador_id' => $row->coordinador_id,
                'e14_estado' => $row->e14_reportado_at ? 'Sí' : 'No',
                'control_estado' => $row->control_final_at ? 'Completo' : 'Pendiente',
                'balance_alerta' => $balance['status'],
                'balance_resumen' => $balance['summary'],
                'balance_expected' => $balance['expected'],
                'balance_actual' => $balance['actual'],
                'balance_total_votos' => $balance['votes_total'],
                'reclamacion_alerta' => $hasReclamacion ? 'Alerta' : 'OK',
                'reclamacion_resumen' => $hasReclamacion ? (($row->reclamacion_origen ?? '') === 'nuestra' ? 'Nuestra' : 'Otro candidato') : 'No',
                'reconteo_alerta' => $hasReconteo ? 'Alerta' : 'OK',
                'reconteo_resumen' => $hasReconteo ? 'Sí' : 'No',
                'jurados_firmaron' => is_null($jurados) ? 'Pendiente' : (string) $jurados,
                'jurados_alerta' => $juradosAlerta,
                'row' => $row,
            ];
        })->values();
    }

    protected function mesaBalanceSnapshot($row): array
    {
        $detalle = $row->e14_detalle;

        if (is_string($detalle)) {
            $decoded = json_decode($detalle, true);
            $detalle = is_array($decoded) ? $decoded : null;
        }

        $required = [
            $row->votos_en_urna,
            $row->votos_incinerados,
            $row->votos_blanco,
            $row->votos_nulos,
            $row->votos_no_marcados,
        ];

        if (collect($required)->contains(fn ($value) => is_null($value)) || !is_array($detalle)) {
            return [
                'status' => 'Pendiente',
                'summary' => 'Pendiente',
                'expected' => null,
                'actual' => null,
                'votes_total' => null,
            ];
        }

        $candidateVotes = collect($detalle)->sum(function ($item) {
            return (int) ($item['votos'] ?? 0);
        });

        $totalVotos = $candidateVotes
            + (int) $row->votos_blanco
            + (int) $row->votos_nulos
            + (int) $row->votos_no_marcados;

        $actual = $totalVotos - (int) $row->votos_incinerados;
        $expected = (int) $row->votos_en_urna;
        $ok = $actual === $expected;

        return [
            'status' => $ok ? 'OK' : 'Alerta',
            'summary' => $actual . ' / ' . $expected,
            'expected' => $expected,
            'actual' => $actual,
            'votes_total' => $totalVotos,
        ];
    }

    protected function loadCoordinadoresPorPuesto(int $eleccionId, int $puestoId): Collection
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
            ->get([
                'ac.id',
                'ac.abogado_id',
                'ac.validacion_estado',
                'ac.eleccion_mesa_id',
                'a.nombre',
                'a.telefono',
                'a.correo as email',
                DB::raw('COALESCE(ep_mesa.id, ep_cod.id) as puesto_id'),
                DB::raw('COALESCE(em.mesa_num, NULL) as mesa_num'),
            ])
            ->filter(fn ($row) => (int) ($row->puesto_id ?? 0) === $puestoId)
            ->sortBy('nombre')
            ->values();
    }
}
