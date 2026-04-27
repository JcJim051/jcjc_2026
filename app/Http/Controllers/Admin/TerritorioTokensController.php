<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use App\Models\EleccionPuesto;
use App\Models\TerritorioToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TerritorioTokensController extends Controller
{
    public function index()
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        $tokens = TerritorioToken::orderByDesc('id')->get();

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
}
