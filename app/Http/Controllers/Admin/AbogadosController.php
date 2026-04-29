<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abogado;
use App\Models\AbogadoCoordinacion;
use App\Models\Eleccion;
use App\Models\Puestos;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AbogadosController extends Controller
{
    public function index()
    {
        $abogados = Abogado::orderByDesc('activo')->orderBy('nombre')->get();
        return view('admin.abogados.index', compact('abogados'));
    }

    public function create()
    {
        $puestos = Puestos::query()
            ->orderBy('mun')
            ->orderBy('nombre')
            ->get(['mun', 'nombre'])
            ->map(function ($p) {
                return trim(($p->mun ?? '') . ' - ' . ($p->nombre ?? ''), ' -');
            })
            ->filter()
            ->unique()
            ->values();

        return view('admin.abogados.create', compact('puestos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cc' => 'required|string|max:50',
            'correo' => 'required|email|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:50',
            'comuna' => 'required|string|max:255',
            'puesto' => 'required|string|max:255',
            'mesa' => 'required|string|max:255',
            'estudios' => 'nullable|string|max:255',
            'titulo' => 'nullable|string|max:255',
            'entidad' => 'nullable|string|max:255',
            'secretaria' => 'nullable|string|max:255',
            'honorarios' => 'nullable|string|max:255',
            'disponibilidad' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:4096',
            'pdf_cc' => 'nullable|file|mimes:pdf|max:4096',
            'activo' => 'nullable|boolean',
        ]);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('abogados/fotos', 'public');
        }
        if ($request->hasFile('pdf_cc')) {
            $data['pdf_cc'] = $request->file('pdf_cc')->store('abogados/cc', 'public');
        }
        $data['activo'] = (bool) ($data['activo'] ?? true);
        Abogado::create($data);

        return redirect()->route('admin.abogados.index')->with('info', 'Abogado creado correctamente.');
    }

    public function show(Abogado $abogado)
    {
        $abogado->load(['reuniones' => function ($q) {
            $q->orderByDesc('fecha');
        }]);
        [$municipioVota, $puestoVota] = $this->splitPuestoTexto($abogado->puesto);
        $puestosMunicipio = collect();

        if ($municipioVota) {
            $puestosMunicipio = Puestos::query()
                ->where('mun', $municipioVota)
                ->orderBy('codpuesto')
                ->orderBy('nombre')
                ->get()
                ->map(function ($p) use ($puestoVota) {
                    $totalRem = Seller::where('codcor', $p->codpuesto)->where('mesa', 'Rem')->count();
                    $ocupadosRem = Seller::where('codcor', $p->codpuesto)
                        ->where('mesa', 'Rem')
                        ->where(function ($q) {
                            $q->whereNotNull('cedula')->where('cedula', '<>', '')
                              ->orWhere(function ($q2) {
                                  $q2->whereNotNull('nombre')->where('nombre', '<>', '');
                              });
                        })
                        ->count();

                    return (object) [
                        'codpuesto' => $p->codpuesto,
                        'label' => trim(($p->mun ?? '') . ' - ' . ($p->nombre ?? ''), ' -'),
                        'total_rem' => $totalRem,
                        'ocupados_rem' => $ocupadosRem,
                        'disponibles_rem' => max(0, $totalRem - $ocupadosRem),
                        'es_puesto_vota' => mb_strtoupper(trim((string) $p->nombre)) === mb_strtoupper(trim((string) $puestoVota)),
                    ];
                })
                ->values();
        }

        $elecciones = Eleccion::query()->orderByDesc('id')->get(['id', 'nombre', 'tipo', 'estado']);
        $eleccionActiva = Eleccion::where('estado', 'activa')->max('id');
        $historialCoordinaciones = AbogadoCoordinacion::query()
            ->from('abogado_coordinaciones as ac')
            ->leftJoin('elecciones as e', 'e.id', '=', 'ac.eleccion_id')
            ->leftJoin('puestos as p', 'p.codpuesto', '=', 'ac.codpuesto')
            ->where('ac.abogado_id', $abogado->id)
            ->orderByDesc('ac.assigned_at')
            ->select(
                'ac.*',
                'e.nombre as eleccion_nombre',
                'e.tipo as eleccion_tipo',
                'p.mun as puesto_municipio',
                'p.nombre as puesto_nombre'
            )
            ->get();

        return view('admin.abogados.show', compact(
            'abogado',
            'puestosMunicipio',
            'municipioVota',
            'elecciones',
            'eleccionActiva',
            'historialCoordinaciones'
        ));
    }

    public function edit(Abogado $abogado)
    {
        $puestos = Puestos::query()
            ->orderBy('mun')
            ->orderBy('nombre')
            ->get(['mun', 'nombre'])
            ->map(function ($p) {
                return trim(($p->mun ?? '') . ' - ' . ($p->nombre ?? ''), ' -');
            })
            ->filter()
            ->unique()
            ->values();

        return view('admin.abogados.edit', compact('abogado', 'puestos'));
    }

    public function update(Request $request, Abogado $abogado)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cc' => 'required|string|max:50',
            'correo' => 'required|email|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:50',
            'comuna' => 'required|string|max:255',
            'puesto' => 'required|string|max:255',
            'mesa' => 'required|string|max:255',
            'estudios' => 'nullable|string|max:255',
            'titulo' => 'nullable|string|max:255',
            'entidad' => 'nullable|string|max:255',
            'secretaria' => 'nullable|string|max:255',
            'honorarios' => 'nullable|string|max:255',
            'disponibilidad' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:4096',
            'pdf_cc' => 'nullable|file|mimes:pdf|max:4096',
            'activo' => 'nullable|boolean',
        ]);
        if ($request->hasFile('foto')) {
            if ($abogado->foto) {
                Storage::disk('public')->delete($abogado->foto);
            }
            $data['foto'] = $request->file('foto')->store('abogados/fotos', 'public');
        }
        if ($request->hasFile('pdf_cc')) {
            if ($abogado->pdf_cc) {
                Storage::disk('public')->delete($abogado->pdf_cc);
            }
            $data['pdf_cc'] = $request->file('pdf_cc')->store('abogados/cc', 'public');
        }
        $data['activo'] = (bool) ($data['activo'] ?? false);
        $abogado->update($data);

        return redirect()->route('admin.abogados.index')->with('info', 'Abogado actualizado correctamente.');
    }

    public function destroy(Abogado $abogado)
    {
        $abogado->delete();
        return redirect()->route('admin.abogados.index')->with('info', 'Abogado eliminado correctamente.');
    }

    public function asignarCoordinador(Request $request, Abogado $abogado)
    {
        $data = $request->validate([
            'codpuesto' => 'required|string|exists:puestos,codpuesto',
            'eleccion_id' => 'required|integer|exists:elecciones,id',
        ]);

        [$municipioVota] = $this->splitPuestoTexto($abogado->puesto);
        $puesto = Puestos::where('codpuesto', $data['codpuesto'])->firstOrFail();

        if ($municipioVota && mb_strtoupper(trim((string) $puesto->mun)) !== mb_strtoupper(trim((string) $municipioVota))) {
            return back()->withErrors(['codpuesto' => 'Solo puedes asignar en el municipio donde vota.']);
        }

        try {
            DB::transaction(function () use ($abogado, $puesto, $data) {
                $yaAsignado = AbogadoCoordinacion::where('abogado_id', $abogado->id)
                    ->where('eleccion_id', $data['eleccion_id'])
                    ->whereNull('released_at')
                    ->exists();
                if ($yaAsignado) {
                    throw new \RuntimeException('Esta persona ya tiene una coordinación activa en esta elección.');
                }

                $slot = Seller::where('codcor', $puesto->codpuesto)
                    ->where('mesa', 'Rem')
                    ->where(function ($q) {
                        $q->whereNull('cedula')->orWhere('cedula', '');
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$slot) {
                    throw new \RuntimeException('No hay cupos remanentes disponibles en este puesto.');
                }

                $slot->update([
                    'cedula' => $abogado->cc,
                    'nombre' => $abogado->nombre,
                    'email' => $abogado->correo,
                    'telefono' => $abogado->telefono,
                    'statusasistencia' => 0,
                    'status' => 0,
                    'observacion' => trim(($slot->observacion ? $slot->observacion.' | ' : '').'Asignado desde caracterización abogados'),
                ]);

                AbogadoCoordinacion::create([
                    'abogado_id' => $abogado->id,
                    'eleccion_id' => $data['eleccion_id'],
                    'codpuesto' => $puesto->codpuesto,
                    'seller_id' => $slot->id,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => Carbon::now('America/Bogota'),
                    'validacion_estado' => 'asignado',
                    'observacion' => 'Asignado a remanente desde caracterización abogados',
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['codpuesto' => $e->getMessage()]);
        }

        return back()->with('info', 'Coordinador asignado correctamente a cupo remanente.');
    }

    private function splitPuestoTexto(?string $puesto): array
    {
        $texto = trim((string) $puesto);
        if ($texto === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*-\s*/', $texto, 2);
        if (count($parts) < 2) {
            return [$texto, null];
        }

        return [trim($parts[0]), trim($parts[1])];
    }
}
