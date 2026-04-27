<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class EleccionesController extends Controller
{
    public function index()
    {
        $elecciones = Eleccion::orderByDesc('id')->get();
        return view('admin.elecciones.index', compact('elecciones'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:100',
            'fecha' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'alcance_tipo' => 'nullable|string|max:50',
            'alcance_dd' => 'nullable|string|max:2',
            'alcance_mm' => 'nullable|string|max:255',
            'divipol' => 'nullable|file|mimes:xlsx',
        ]);
        $alcanceDd = $this->normalizeDd($data['alcance_dd'] ?? null);
        $alcanceMm = $this->normalizeMmCsv($data['alcance_mm'] ?? null);

        $eleccion = Eleccion::create([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'fecha' => $data['fecha'] ?? null,
            'estado' => $data['estado'],
            'alcance_tipo' => $data['alcance_tipo'] ?? null,
            'alcance_dd' => $alcanceDd,
            'alcance_mm' => $alcanceMm,
        ]);

        if ($request->hasFile('divipol')) {
            $result = $this->importDivipol($request->file('divipol'), $eleccion->id);
            if ($result !== true) {
                return redirect()->route('admin.elecciones.index')
                    ->with('error', 'Eleccion creada, pero fallo la importacion DIVIPOL: ' . $result);
            }
        }

        return redirect()->route('admin.elecciones.index')
            ->with('success', 'Eleccion creada correctamente.');
    }

    public function import(Request $request, Eleccion $eleccion)
    {
        $request->validate([
            'divipol' => 'required|file|mimes:xlsx',
        ]);

        $result = $this->importDivipol($request->file('divipol'), $eleccion->id);
        if ($result !== true) {
            return redirect()->route('admin.elecciones.index')
                ->with('error', 'Fallo la importacion DIVIPOL: ' . $result);
        }

        return redirect()->route('admin.elecciones.index')
            ->with('success', 'DIVIPOL importado correctamente para la eleccion ' . $eleccion->id);
    }

    public function update(Request $request, Eleccion $eleccion)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|max:100',
            'fecha' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'alcance_tipo' => 'nullable|string|max:50',
            'alcance_dd' => 'nullable|string|max:2',
            'alcance_mm' => 'nullable|string|max:255',
        ]);
        $alcanceDd = $this->normalizeDd($data['alcance_dd'] ?? null);
        $alcanceMm = $this->normalizeMmCsv($data['alcance_mm'] ?? null);

        $eleccion->update([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'fecha' => $data['fecha'] ?? null,
            'estado' => $data['estado'],
            'alcance_tipo' => $data['alcance_tipo'] ?? null,
            'alcance_dd' => $alcanceDd,
            'alcance_mm' => $alcanceMm,
        ]);

        return redirect()->route('admin.elecciones.index')
            ->with('success', 'Eleccion actualizada correctamente.');
    }

    private function importDivipol($file, int $eleccionId)
    {
        $path = $file->store('divipol_uploads', 'local');
        $fullPath = storage_path('app/' . $path);

        $eleccion = Eleccion::find($eleccionId);

        $exitCode = Artisan::call('divipol:import-u101', [
            'file' => $fullPath,
            'eleccion_id' => $eleccionId,
            '--dd' => $eleccion?->alcance_dd ?: null,
            '--mm' => $eleccion?->alcance_mm ?: null,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return $output !== '' ? $output : 'Error desconocido al ejecutar el comando.';
        }

        return true;
    }

    private function normalizeDd(?string $dd): ?string
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

    private function normalizeMmCsv(?string $mm): ?string
    {
        if ($mm === null) {
            return null;
        }
        $mm = trim($mm);
        if ($mm === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', $mm));
        $out = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\d+$/', $part)) {
                $part = str_pad($part, 3, '0', STR_PAD_LEFT);
            }
            $out[] = $part;
        }
        $out = array_values(array_unique($out));
        return $out ? implode(',', $out) : null;
    }
}
