<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\Eleccion;
use App\Traits\EleccionScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CandidatosController extends Controller
{
    use EleccionScope;

    public function index(Request $request)
    {
        $eleccionId = $this->resolveEleccionId((int) $request->get('eleccion_id'));

        $elecciones = Eleccion::orderByDesc('id')->get();
        $candidatos = Candidato::query()
            ->when($eleccionId, fn ($q) => $q->where('eleccion_id', $eleccionId))
            ->orderBy('codigo')
            ->get();

        return view('admin.candidatos.index', compact('candidatos', 'elecciones', 'eleccionId'));
    }

    public function create()
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        return view('admin.candidatos.create', compact('elecciones'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'codigo' => [
                'required',
                'integer',
                Rule::unique('candidatos')->where(fn ($q) => $q->where('eleccion_id', $request->eleccion_id)),
            ],
            'nombre' => 'required|string|max:255',
            'partido' => 'nullable|string|max:255',
            'campo_e14' => [
                'nullable',
                'integer',
                'min:1',
                'max:11',
                Rule::unique('candidatos')
                    ->where(fn ($q) => $q->where('eleccion_id', $request->eleccion_id)),
            ],
            'activo' => 'required|in:0,1',
        ]);

        Candidato::create($data);

        return redirect()->route('admin.candidatos.index')
            ->with('success', 'Candidato creado correctamente.');
    }

    public function edit(Candidato $candidato)
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        return view('admin.candidatos.edit', compact('candidato', 'elecciones'));
    }

    public function update(Request $request, Candidato $candidato)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'codigo' => [
                'required',
                'integer',
                Rule::unique('candidatos')
                    ->ignore($candidato->id)
                    ->where(fn ($q) => $q->where('eleccion_id', $request->eleccion_id)),
            ],
            'nombre' => 'required|string|max:255',
            'partido' => 'nullable|string|max:255',
            'campo_e14' => [
                'nullable',
                'integer',
                'min:1',
                'max:11',
                Rule::unique('candidatos')
                    ->ignore($candidato->id)
                    ->where(fn ($q) => $q->where('eleccion_id', $request->eleccion_id)),
            ],
            'activo' => 'required|in:0,1',
        ]);

        $candidato->update($data);

        return redirect()->route('admin.candidatos.index')
            ->with('success', 'Candidato actualizado correctamente.');
    }

    public function destroy(Candidato $candidato)
    {
        $candidato->delete();
        return redirect()->route('admin.candidatos.index')
            ->with('success', 'Candidato eliminado correctamente.');
    }
}
