<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\Seller;
use App\Traits\Compartimentacion;
use App\Traits\EleccionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileE14Controller extends Controller
{
    use Compartimentacion;
    use EleccionScope;

    public function sellers(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = $this->filtrarSellersPorUsuario($user)
            ->sortBy([
                ['municipio', 'asc'],
                ['puesto', 'asc'],
                ['mesa', 'asc'],
            ])
            ->take(500)
            ->values();

        $items = $rows->map(function ($seller) {
            return [
                'id' => (int) $seller->id,
                'municipio' => (string) $seller->municipio,
                'puesto' => (string) $seller->puesto,
                'mesa' => (string) $seller->mesa,
                'reportado' => !is_null($seller->gob1),
            ];
        });

        return response()->json([
            'items' => $items,
        ]);
    }

    public function form(Request $request): JsonResponse
    {
        $seller = $this->findAuthorizedSeller($request);
        $eleccionId = $this->resolveEleccionId((int) $request->query('eleccion_id'));

        $candidateRows = Candidato::query()
            ->where('activo', 1)
            ->whereNotNull('campo_e14')
            ->when($eleccionId, fn ($q) => $q->where('eleccion_id', $eleccionId))
            ->orderBy('campo_e14')
            ->orderBy('codigo')
            ->get()
            ->map(function ($c) {
                return [
                    'codigo' => (string) $c->codigo,
                    'nombre' => (string) $c->nombre,
                    'partido' => (string) ($c->partido ?? ''),
                    'campo' => 'gob' . (int) $c->campo_e14,
                ];
            })
            ->values();

        if ($candidateRows->isEmpty()) {
            $candidateRows = collect($this->fallbackCandidates());
        }

        $candidateFields = $candidateRows->pluck('campo')->values()->all();

        $candidateValues = [];
        foreach ($candidateFields as $field) {
            $candidateValues[$field] = (int) ($seller->{$field} ?? 0);
        }

        $reclamacionOptions = [['value' => '0', 'label' => 'No']];
        foreach ($candidateRows as $row) {
            $reclamacionOptions[] = [
                'value' => (string) $row['codigo'],
                'label' => (string) $row['nombre'],
            ];
        }

        return response()->json([
            'seller' => [
                'id' => (int) $seller->id,
                'municipio' => (string) $seller->municipio,
                'puesto' => (string) $seller->puesto,
                'mesa' => (string) $seller->mesa,
            ],
            'candidates' => $candidateRows,
            'candidate_values' => $candidateValues,
            'fields' => [
                'censodemesa' => (int) ($seller->censodemesa ?? 0),
                'votosenurna' => (int) ($seller->votosenurna ?? 0),
                'votosincinerados' => (int) ($seller->votosincinerados ?? 0),
                'enblanco' => (int) ($seller->enblanco ?? 0),
                'nulos' => (int) ($seller->nulos ?? 0),
                'nomarcados' => (int) ($seller->nomarcados ?? 0),
                'status_reconteo' => (string) ($seller->status_reconteo ?? '0'),
                'reclamacion' => (string) ($seller->reclamacion ?? '0'),
            ],
            'reclamacion_options' => $reclamacionOptions,
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $seller = $this->findAuthorizedSeller($request);
        $eleccionId = $this->resolveEleccionId((int) $request->input('eleccion_id'));

        $candidateRows = Candidato::query()
            ->where('activo', 1)
            ->whereNotNull('campo_e14')
            ->when($eleccionId, fn ($q) => $q->where('eleccion_id', $eleccionId))
            ->orderBy('campo_e14')
            ->orderBy('codigo')
            ->get()
            ->map(function ($c) {
                return [
                    'codigo' => (string) $c->codigo,
                    'campo' => 'gob' . (int) $c->campo_e14,
                ];
            })
            ->values();

        if ($candidateRows->isEmpty()) {
            $candidateRows = collect($this->fallbackCandidates())->map(function ($row) {
                return [
                    'codigo' => (string) $row['codigo'],
                    'campo' => (string) $row['campo'],
                ];
            });
        }

        $candidateFields = $candidateRows->pluck('campo')->unique()->values()->all();
        $candidateCodes = $candidateRows->pluck('codigo')->unique()->values()->all();

        $rules = [
            'censodemesa' => ['required', 'integer', 'min:0'],
            'votosenurna' => ['required', 'integer', 'min:0'],
            'votosincinerados' => ['required', 'integer', 'min:0'],
            'enblanco' => ['required', 'integer', 'min:0'],
            'nulos' => ['required', 'integer', 'min:0'],
            'nomarcados' => ['required', 'integer', 'min:0'],
            'status_reconteo' => ['required', Rule::in(['0', '1', 0, 1])],
            'reclamacion' => ['required', Rule::in(array_merge(['0', 0], $candidateCodes))],
        ];

        foreach ($candidateFields as $field) {
            $rules[$field] = ['required', 'integer', 'min:0'];
        }

        $data = $request->validate($rules);

        $update = [
            'censodemesa' => (int) $data['censodemesa'],
            'votosenurna' => (int) $data['votosenurna'],
            'votosincinerados' => (int) $data['votosincinerados'],
            'enblanco' => (int) $data['enblanco'],
            'nulos' => (int) $data['nulos'],
            'nomarcados' => (int) $data['nomarcados'],
            'status_reconteo' => (string) $data['status_reconteo'],
            'reclamacion' => (string) $data['reclamacion'],
            'modificadopor' => (string) $request->user()->name,
        ];

        foreach ($candidateFields as $field) {
            $update[$field] = (int) $data[$field];
        }

        $seller->update($update);

        return response()->json([
            'message' => 'Reporte E14 guardado correctamente.',
            'seller_id' => (int) $seller->id,
        ]);
    }

    private function findAuthorizedSeller(Request $request): Seller
    {
        $sellerId = (int) $request->input('seller_id', $request->query('seller_id'));

        $authorized = $this->filtrarSellersPorUsuario($request->user())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        abort_unless(in_array($sellerId, $authorized, true), 403, 'No autorizado para esta mesa.');

        return Seller::findOrFail($sellerId);
    }

    private function fallbackCandidates(): array
    {
        return [
            ['codigo' => '01', 'nombre' => 'Wilmar Barbosa', 'partido' => '', 'campo' => 'gob4'],
            ['codigo' => '02', 'nombre' => 'Bairon Munoz', 'partido' => '', 'campo' => 'gob7'],
            ['codigo' => '03', 'nombre' => 'Jose L Silva', 'partido' => '', 'campo' => 'gob11'],
            ['codigo' => '04', 'nombre' => 'Harold Barreto', 'partido' => '', 'campo' => 'gob6'],
            ['codigo' => '05', 'nombre' => 'Rafaela Cortes', 'partido' => '', 'campo' => 'gob1'],
            ['codigo' => '06', 'nombre' => 'Antonio Amaya', 'partido' => '', 'campo' => 'gob8'],
            ['codigo' => '07', 'nombre' => 'Edward Libreros', 'partido' => '', 'campo' => 'gob5'],
            ['codigo' => '08', 'nombre' => 'Florentino Vasquez', 'partido' => '', 'campo' => 'gob9'],
            ['codigo' => '09', 'nombre' => 'Marcela Amaya', 'partido' => '', 'campo' => 'gob2'],
            ['codigo' => '10', 'nombre' => 'Dario Vasquez', 'partido' => '', 'campo' => 'gob3'],
        ];
    }
}
