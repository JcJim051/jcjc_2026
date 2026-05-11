<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use App\Models\EleccionPuesto;
use App\Models\Referido;
use App\Models\TerritorioToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TerritorioTokensController extends Controller
{
    public function index()
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        $tokens = TerritorioToken::orderByDesc('id')->get();

        $municipiosMap = EleccionPuesto::query()
            ->whereIn('eleccion_id', $tokens->pluck('eleccion_id')->unique()->values())
            ->select('eleccion_id', 'dd', 'mm', 'departamento', 'municipio')
            ->distinct()
            ->get()
            ->keyBy(function ($r) {
                return $r->eleccion_id . '|' . $r->dd . '|' . $r->mm;
            });

        $tokens = $tokens->map(function ($t) use ($municipiosMap) {
            $key = $t->eleccion_id . '|' . $t->dd . '|' . $t->mm;
            $geo = $municipiosMap->get($key);
            $t->departamento_nombre = $geo->departamento ?? null;
            $t->municipio_nombre = $geo->municipio ?? null;
            $metaPct = (int) (Eleccion::where('id', $t->eleccion_id)->value('meta_testigos_pct') ?? 100);
            $metaPct = max(0, min(100, $metaPct));
            $t->meta_testigos_pct = $metaPct;

            $mesasQuery = DB::table('eleccion_mesas as em')
                ->join('eleccion_puestos as ep', 'ep.id', '=', 'em.eleccion_puesto_id')
                ->where('em.eleccion_id', $t->eleccion_id)
                ->where('ep.dd', $t->dd)
                ->where('ep.mm', $t->mm);
            if (!empty($t->comuna)) {
                $mesasQuery->where('ep.comuna', $t->comuna);
            }
            $t->mesas_total = (int) $mesasQuery->count();

            $puestos = DB::table('eleccion_puestos as ep')
                ->where('ep.eleccion_id', $t->eleccion_id)
                ->where('ep.dd', $t->dd)
                ->where('ep.mm', $t->mm)
                ->when(!empty($t->comuna), function ($q) use ($t) {
                    $q->where('ep.comuna', $t->comuna);
                })
                ->pluck('ep.id');

            $metaObjetivo = 0;
            if ($puestos->isNotEmpty()) {
                $mesasByPuesto = DB::table('eleccion_mesas')
                    ->selectRaw('eleccion_puesto_id, COUNT(*) as total_mesas')
                    ->where('eleccion_id', $t->eleccion_id)
                    ->whereIn('eleccion_puesto_id', $puestos->all())
                    ->groupBy('eleccion_puesto_id')
                    ->get();

                foreach ($mesasByPuesto as $row) {
                    $metaObjetivo += (int) floor(((int) $row->total_mesas) * ($metaPct / 100));
                }
            }
            $t->meta_objetivo = $metaObjetivo;

            $t->ocupados_total = (int) Referido::query()
                ->where('territorio_token_id', $t->id)
                ->where('estado', '<>', 'rechazado')
                ->count();
            $t->referidos_total = $t->ocupados_total;
            $t->faltan_total = max($metaObjetivo - $t->ocupados_total, 0);
            return $t;
        });

        return view('admin.territorio_tokens.index', compact('elecciones', 'tokens'));
    }

    public function municipios(Request $request)
    {
        $eleccionId = (int) $request->get('eleccion_id');
        $search = trim((string) $request->get('q', ''));

        $query = EleccionPuesto::where('eleccion_id', $eleccionId);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('departamento', 'like', '%' . $search . '%')
                  ->orWhere('municipio', 'like', '%' . $search . '%');
            });
        }

        $items = $query
            ->select('dd', 'mm', 'departamento', 'municipio')
            ->distinct()
            ->orderBy('departamento')
            ->orderBy('municipio')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->dd . '-' . $r->mm,
                    'text' => $r->departamento . ' / ' . $r->municipio,
                    'dd' => $r->dd,
                    'mm' => $r->mm,
                ];
            });

        return response()->json(['results' => $items]);
    }

    public function comunas(Request $request)
    {
        $eleccionId = (int) $request->get('eleccion_id');
        $municipioCodigo = (string) $request->get('municipio_codigo', '');
        $search = trim((string) $request->get('q', ''));

        if ($municipioCodigo === '' || strpos($municipioCodigo, '-') === false) {
            return response()->json(['results' => []]);
        }

        [$dd, $mm] = explode('-', $municipioCodigo);

        $query = EleccionPuesto::where('eleccion_id', $eleccionId)
            ->where('dd', $dd)
            ->where('mm', $mm)
            ->whereNotNull('comuna')
            ->select('comuna')
            ->distinct();

        if ($search !== '') {
            $query->where('comuna', 'like', '%' . $search . '%');
        }

        $items = $query->orderBy('comuna')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->comuna,
                'text' => $r->comuna,
            ]);

        return response()->json(['results' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'municipio_codigo' => 'required|string',
            'comuna' => 'nullable|string|max:100',
            'responsable' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
        ]);

        [$dd, $mm] = explode('-', $data['municipio_codigo']);

        $token = Str::random(32);

        TerritorioToken::create([
            'eleccion_id' => $data['eleccion_id'],
            'dd' => $dd,
            'mm' => $mm,
            'comuna' => $data['comuna'] ?? null,
            'token' => $token,
            'responsable' => $data['responsable'] ?? null,
            'activo' => true,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return redirect()->route('admin.territorio_tokens.index')
            ->with('success', 'Token creado correctamente.');
    }

    public function toggle(TerritorioToken $token)
    {
        $token->activo = !$token->activo;
        $token->save();

        return redirect()->route('admin.territorio_tokens.index')
            ->with('success', 'Estado del token actualizado.');
    }

    public function destroy(TerritorioToken $token)
    {
        $token->delete();

        return redirect()->route('admin.territorio_tokens.index')
            ->with('success', 'Token eliminado correctamente.');
    }
}
