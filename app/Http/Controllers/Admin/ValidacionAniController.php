<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbogadoCoordinacion;
use App\Models\EleccionPuesto;
use App\Models\Persona;
use App\Models\Referido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Traits\EleccionScope;

class ValidacionAniController extends Controller
{
    use EleccionScope;
    public function index()
    {
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
        $query = DB::table('referidos as r')
            ->join('personas as p', 'p.id', '=', 'r.persona_id')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'r.eleccion_puesto_id')
            ->select([
                'r.id',
                'r.estado',
                'r.cedula_pdf_path',
                'p.cedula',
                'p.nombre',
                'p.email',
                'ep.municipio',
                'ep.puesto',
                'r.mesa_num',
            ])
            ->when($eleccionId, function ($q) use ($eleccionId) {
                $q->where('r.eleccion_id', $eleccionId);
            })
            ->whereIn('r.estado', ['asignado', 'validado'])
            ->orderByDesc('r.id');
        if ($eleccionId) {
            $this->applyEleccionScope($query, $eleccionId, 'ep');
        }
        $referidos = $query->get();

        $coordinadores = AbogadoCoordinacion::query()
            ->from('abogado_coordinaciones as ac')
            ->join('abogados as a', 'a.id', '=', 'ac.abogado_id')
            ->leftJoin('puestos as pu', 'pu.codpuesto', '=', 'ac.codpuesto')
            ->where('ac.eleccion_id', $eleccionId)
            ->whereNull('ac.released_at')
            ->select([
                DB::raw("CONCAT('coord-', ac.id) as id"),
                'ac.id as coordinacion_id',
                DB::raw("COALESCE(ac.validacion_estado, 'asignado') as estado"),
                'a.pdf_cc as cedula_pdf_path',
                'a.cc as cedula',
                'a.nombre',
                'a.correo as email',
                DB::raw('COALESCE(pu.mun, "") as municipio'),
                DB::raw('COALESCE(pu.nombre, ac.codpuesto) as puesto'),
                DB::raw("'Rem' as mesa_num"),
                DB::raw("'coordinador' as tipo_fila"),
                'a.id as abogado_id',
            ])
            ->get();

        $referidos = $referidos->map(function ($r) {
            $r->tipo_fila = 'referido';
            $r->abogado_id = null;
            $r->coordinacion_id = null;
            return $r;
        })->concat($coordinadores)->values();

        return view('admin.validacion_ani.index', compact('referidos'));
    }

    public function editCoordinador(AbogadoCoordinacion $coordinacion)
    {
        $abogado = \App\Models\Abogado::findOrFail($coordinacion->abogado_id);
        $puesto = \App\Models\Puestos::where('codpuesto', $coordinacion->codpuesto)->first();
        if ($abogado->pdf_cc && str_starts_with($abogado->pdf_cc, 'abogados/cc/')) {
            $filename = basename($abogado->pdf_cc);
            $target = 'cedulas/' . $filename;
            if (Storage::disk('public')->exists($abogado->pdf_cc) && !Storage::disk('public')->exists($target)) {
                Storage::disk('public')->copy($abogado->pdf_cc, $target);
            }
            if (Storage::disk('public')->exists($target)) {
                $abogado->pdf_cc = $target;
                $abogado->save();
            }
        }
        $pdfUrl = $abogado->pdf_cc ? asset('storage/' . $abogado->pdf_cc) : null;

        return view('admin.validacion_ani.edit_coordinador', compact('coordinacion', 'abogado', 'puesto', 'pdfUrl'));
    }

    public function updateCoordinador(Request $request, AbogadoCoordinacion $coordinacion)
    {
        $abogado = \App\Models\Abogado::findOrFail($coordinacion->abogado_id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cedula' => ['required', 'string', 'max:50', Rule::unique('abogados', 'cc')->ignore($abogado->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('abogados', 'correo')->ignore($abogado->id)],
            'telefono' => 'nullable|string|max:50',
            'nueva_observacion' => 'nullable|string',
            'estado' => 'required|in:asignado,validado',
            'pdf' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        $estadoAnterior = (string) ($coordinacion->validacion_estado ?? 'asignado');

        $abogado->nombre = $data['nombre'];
        $abogado->cc = $data['cedula'];
        $abogado->correo = $data['email'];
        $abogado->telefono = $data['telefono'] ?? null;

        if ($request->hasFile('pdf')) {
            $abogado->pdf_cc = $request->file('pdf')->store('cedulas', 'public');
        }
        $abogado->save();

        $coordinacion->validacion_estado = $data['estado'];
        $lineas = [];
        if ($estadoAnterior !== (string) $data['estado']) {
            $lineas[] = 'Estado: ' . $estadoAnterior . ' -> ' . $data['estado'];
        }
        $nuevaObs = trim((string) ($data['nueva_observacion'] ?? ''));
        if ($nuevaObs !== '') {
            $lineas[] = 'Nota ANI: ' . $nuevaObs;
        }
        foreach ($lineas as $linea) {
            $this->appendAuditCoordinacion($coordinacion, $linea);
        }
        $coordinacion->save();

        return redirect()->route('admin.validacion_ani.index')
            ->with('success', 'Validación de coordinador actualizada.');
    }

    public function edit(Referido $referido)
    {
        $persona = Persona::find($referido->persona_id);
        $puesto = EleccionPuesto::find($referido->eleccion_puesto_id);
        $pdfUrl = $referido->cedula_pdf_path ? asset('storage/' . $referido->cedula_pdf_path) : null;

        $puestos = EleccionPuesto::where('eleccion_id', $referido->eleccion_id)
            ->orderBy('municipio')
            ->orderBy('puesto')
            ->get();

        return view('admin.validacion_ani.edit', compact('referido', 'persona', 'puesto', 'puestos', 'pdfUrl'));
    }

    public function update(Request $request, Referido $referido)
    {
        $persona = Persona::findOrFail($referido->persona_id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cedula' => ['required', 'string', 'max:50', Rule::unique('personas', 'cedula')->ignore($persona->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('personas', 'email')->ignore($persona->id)],
            'telefono' => 'nullable|string|max:50',
            'nueva_observacion' => 'nullable|string',
            'estado' => 'required|in:asignado,validado',
            'eleccion_puesto_id' => 'required|integer|exists:eleccion_puestos,id',
            'mesa_num' => 'required|integer|min:1',
            'pdf' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        $estadoAnterior = (string) $referido->estado;
        $puestoAnterior = (int) $referido->eleccion_puesto_id;
        $mesaAnterior = (int) $referido->mesa_num;

        $persona->nombre = $data['nombre'];
        $persona->nombre_original = $data['nombre'];
        $persona->nombre_normalizado = $this->normalizeName($data['nombre']);
        $persona->cedula = $data['cedula'];
        $persona->email = $data['email'];
        $persona->telefono = $data['telefono'] ?? null;
        $persona->save();

        if ($request->hasFile('pdf')) {
            $path = $request->file('pdf')->store('cedulas', 'public');
            $referido->cedula_pdf_path = $path;
        }

        $referido->eleccion_puesto_id = (int) $data['eleccion_puesto_id'];
        $referido->mesa_num = (int) $data['mesa_num'];
        $referido->estado = $data['estado'];
        $lineas = [];
        if ($estadoAnterior !== (string) $data['estado']) {
            $lineas[] = 'Estado: ' . $estadoAnterior . ' -> ' . $data['estado'];
        }
        if ($puestoAnterior !== (int) $data['eleccion_puesto_id'] || $mesaAnterior !== (int) $data['mesa_num']) {
            $lineas[] = 'Cambio de puesto/mesa: puesto #' . $puestoAnterior . ' mesa ' . $mesaAnterior .
                ' -> puesto #' . (int) $data['eleccion_puesto_id'] . ' mesa ' . (int) $data['mesa_num'];
        }
        $nuevaObs = trim((string) ($data['nueva_observacion'] ?? ''));
        if ($nuevaObs !== '') {
            $lineas[] = 'Nota ANI: ' . $nuevaObs;
        }
        foreach ($lineas as $linea) {
            $this->appendAudit($referido, $linea);
        }
        $referido->save();

        return redirect()->route('admin.validacion_ani.index')
            ->with('success', 'Validacion actualizada.');
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($trans !== false) {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9\\s]/', '', $value);
        $value = preg_replace('/\\s+/', ' ', $value);
        return trim($value);
    }

    private function appendAudit(Referido $referido, string $mensaje): void
    {
        $usuario = Auth::user();
        $actor = $usuario ? ($usuario->name ?? $usuario->email ?? ('user#' . $usuario->id)) : 'sistema';
        $linea = '[' . now()->format('Y-m-d H:i:s') . '] ' . $actor . ': ' . $mensaje;

        $actual = trim((string) ($referido->observaciones ?? ''));
        $referido->observaciones = $actual === '' ? $linea : ($actual . PHP_EOL . $linea);
    }

    private function appendAuditCoordinacion(AbogadoCoordinacion $coordinacion, string $mensaje): void
    {
        $usuario = Auth::user();
        $actor = $usuario ? ($usuario->name ?? $usuario->email ?? ('user#' . $usuario->id)) : 'sistema';
        $linea = '[' . now()->format('Y-m-d H:i:s') . '] ' . $actor . ': ' . $mensaje;

        $actual = trim((string) ($coordinacion->observaciones ?? ''));
        $coordinacion->observaciones = $actual === '' ? $linea : ($actual . PHP_EOL . $linea);
    }
}
