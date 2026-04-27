<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait EleccionScope
{
    protected function resolveEleccionId(?int $requestedId = null): ?int
    {
        if ($requestedId && DB::table('elecciones')->where('id', $requestedId)->exists()) {
            return $requestedId;
        }

        $active = DB::table('elecciones')
            ->where('estado', 'activa')
            ->max('id');
        if ($active) {
            return (int) $active;
        }

        $fromMesas = DB::table('eleccion_mesas')->max('eleccion_id');
        if ($fromMesas) {
            return (int) $fromMesas;
        }

        $fromPuestos = DB::table('eleccion_puestos')->max('eleccion_id');
        if ($fromPuestos) {
            return (int) $fromPuestos;
        }

        return DB::table('elecciones')->max('id');
    }

    protected function applyUserGeoScope($query, $user, string $alias = 'ep'): void
    {
        if ($user->id == 1) {
            return;
        }

        $municipios = $this->splitCsv($user->mun);
        $puestos = $this->splitCsv($user->codpuesto);
        $zonas = $this->splitCsv($user->codzon);

        // Si tiene puestos, restringimos a esos puestos.
        if (!empty($puestos)) {
            $query->where(function ($q) use ($alias, $puestos) {
                $q->whereIn($alias . '.pp', $puestos)
                    ->orWhereIn(DB::raw('CONCAT(' . $alias . '.dd,' . $alias . '.mm,' . $alias . '.zz,' . $alias . '.pp)'), $puestos);
            });
            return;
        }

        // Si tiene municipios, restringimos a esos municipios.
        if (!empty($municipios)) {
            $query->whereIn($alias . '.mm', $municipios);
            return;
        }

        // Si tiene zonas/rutas, restringimos por zona.
        if (!empty($zonas)) {
            $query->whereIn($alias . '.zz', $zonas);
        }
    }

    protected function applyEleccionScope($query, ?int $eleccionId, string $alias = 'ep'): void
    {
        if (!$eleccionId) {
            return;
        }

        $eleccion = DB::table('elecciones')
            ->select('alcance_tipo', 'alcance_dd', 'alcance_mm')
            ->where('id', $eleccionId)
            ->first();

        if (!$eleccion) {
            return;
        }

        $dd = $this->normalizeDd($eleccion->alcance_dd);
        if (!empty($dd)) {
            $query->where($alias . '.dd', $dd);
        }

        $mmList = $this->normalizeMmList($this->splitCsv($eleccion->alcance_mm));
        if (!empty($mmList)) {
            $query->whereIn($alias . '.mm', $mmList);
        }
    }

    protected function applyEleccionScopeSeller($query, ?int $eleccionId, string $alias = 'sellers'): void
    {
        if (!$eleccionId) {
            return;
        }

        $eleccion = DB::table('elecciones')
            ->select('alcance_dd', 'alcance_mm')
            ->where('id', $eleccionId)
            ->first();

        if (!$eleccion) {
            return;
        }

        $dd = $this->normalizeDd($eleccion->alcance_dd);
        if (!empty($dd)) {
            $query->where($alias . '.coddep', $dd);
        }

        $mmList = $this->normalizeMmList($this->splitCsv($eleccion->alcance_mm));
        if (!empty($mmList)) {
            $query->whereIn($alias . '.codmun', $mmList);
        }
    }

    protected function applyCandidateScope($query, $user, string $mesaAlias = 'em', ?int $eleccionId = null): void
    {
        $candidatos = $this->splitCsv($user->candidatos);
        if (empty($candidatos) || in_array('0', $candidatos, true)) {
            return;
        }

        if ($eleccionId) {
            $hasMap = DB::table('eleccion_mesa_candidatos')
                ->where('eleccion_id', $eleccionId)
                ->exists();
            if (!$hasMap) {
                return;
            }
        }

        $query->whereExists(function ($q) use ($candidatos, $mesaAlias) {
            $q->select(DB::raw(1))
                ->from('eleccion_mesa_candidatos as emc')
                ->join('candidatos as c', 'c.id', '=', 'emc.candidato_id')
                ->whereColumn('emc.eleccion_mesa_id', $mesaAlias . '.id')
                ->whereIn('c.codigo', $candidatos);
        });
    }

    protected function splitCsv($value): array
    {
        if (!$value) {
            return [];
        }
        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items, function ($item) {
            if ($item === '' || strtolower($item) === 'all') {
                return false;
            }
            return !preg_match('/^0+$/', $item);
        });

        return array_values($items);
    }

    protected function normalizeDd(?string $dd): ?string
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

    protected function normalizeMmList(array $mmList): array
    {
        if (empty($mmList)) {
            return [];
        }
        $out = [];
        foreach ($mmList as $mm) {
            $mm = trim((string) $mm);
            if ($mm === '') {
                continue;
            }
            if (preg_match('/^\d+$/', $mm)) {
                $mm = str_pad($mm, 3, '0', STR_PAD_LEFT);
            }
            $out[] = $mm;
        }
        return array_values(array_unique($out));
    }
}
