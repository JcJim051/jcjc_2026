<?php

namespace App\Http\Controllers\Admin;;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Referido;
use App\Models\Persona;
use App\Models\EleccionPuesto;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Traits\EleccionScope;

class AniController extends Controller
{
    use EleccionScope;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
        if (!$eleccionId) {
            return view('admin.ani.index', ['mesas' => collect()]);
        }

        $referidoAgg = DB::table('referidos')
            ->where('eleccion_id', $eleccionId)
            ->select([
                'eleccion_puesto_id',
                'mesa_num',
                DB::raw("MAX(CASE WHEN estado = 'acreditado' THEN 1 ELSE 0 END) as has_acreditado"),
                DB::raw("MAX(CASE WHEN estado = 'postulado' THEN 1 ELSE 0 END) as has_postulado"),
                DB::raw("MAX(CASE WHEN estado = 'validado' THEN 1 ELSE 0 END) as has_validado"),
                DB::raw("MAX(CASE WHEN estado = 'asignado' THEN 1 ELSE 0 END) as has_asignado"),
                DB::raw("MAX(CASE WHEN estado = 'referido' THEN 1 ELSE 0 END) as has_referido"),
                DB::raw("COALESCE(
                    MAX(CASE WHEN estado = 'acreditado' THEN id END),
                    MAX(CASE WHEN estado = 'postulado' THEN id END),
                    MAX(CASE WHEN estado = 'validado' THEN id END),
                    MAX(CASE WHEN estado = 'asignado' THEN id END),
                    MAX(CASE WHEN estado = 'referido' THEN id END)
                ) as best_referido_id"),
            ])
            ->groupBy('eleccion_puesto_id', 'mesa_num');

        $mesasQuery = DB::table('eleccion_mesas as em')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'em.eleccion_puesto_id')
            ->leftJoinSub($referidoAgg, 'ra', function ($join) {
                $join->on('ra.eleccion_puesto_id', '=', 'em.eleccion_puesto_id')
                    ->on('ra.mesa_num', '=', 'em.mesa_num');
            })
            ->leftJoin('referidos as r', 'r.id', '=', 'ra.best_referido_id')
            ->leftJoin('personas as p', 'p.id', '=', 'r.persona_id')
            ->where('em.eleccion_id', $eleccionId)
            ->select([
                'em.id as mesa_id',
                'em.mesa_num',
                'em.eleccion_puesto_id',
                'ep.dd',
                'ep.mm',
                'ep.zz',
                'ep.pp',
                'ep.municipio',
                'ep.puesto',
                'ra.has_acreditado',
                'ra.has_postulado',
                'ra.has_validado',
                'ra.has_asignado',
                'ra.has_referido',
                'r.id as referido_id',
                'r.observaciones',
                'p.nombre',
            ]);
        $this->applyEleccionScope($mesasQuery, $eleccionId, 'ep');
        $this->applyUserGeoScope($mesasQuery, $user, 'ep');
        $this->applyCandidateScope($mesasQuery, $user, 'em', $eleccionId);

        $defaultPerPage = $user->id == 1 ? 5000 : 200;
        $perPage = (int) request('per_page', $defaultPerPage);
        if ($perPage <= 0) {
            $perPage = $defaultPerPage;
        }

        $mesasPage = $mesasQuery
            ->orderBy('ep.dd')
            ->orderBy('ep.mm')
            ->orderBy('ep.zz')
            ->orderBy('ep.pp')
            ->orderBy('em.mesa_num')
            ->paginate($perPage)
            ->appends(request()->except('page'));

        $mesasRows = collect($mesasPage->items())->map(function ($row) {
            $estado = 'pendiente';
            if ($row->has_acreditado) {
                $estado = 'acreditado';
            } elseif ($row->has_postulado) {
                $estado = 'postulado';
            } elseif ($row->has_validado) {
                $estado = 'validado';
            } elseif ($row->has_asignado) {
                $estado = 'asignado';
            } elseif ($row->has_referido) {
                $estado = 'referido';
            }

            return (object) [
                'mesa_id' => $row->mesa_id,
                'mesa_label' => (string) $row->mesa_num,
                'mesa_sort' => (int) $row->mesa_num,
                'is_remanente' => false,
                'estado' => $estado,
                'referido_id' => $row->referido_id,
                'observaciones' => $row->observaciones,
                'nombre' => $row->nombre,
                'municipio' => $row->municipio,
                'puesto' => $row->puesto,
                'dd' => $row->dd,
                'mm' => $row->mm,
                'zz' => $row->zz,
                'pp' => $row->pp,
                'eleccion_puesto_id' => $row->eleccion_puesto_id,
            ];
        });

        $remanentesRows = collect();
        $puestosPermitidos = $mesasRows
            ->map(fn ($row) => $row->eleccion_puesto_id)
            ->unique()
            ->flip();

        $puestosQuery = DB::table('eleccion_puestos')
            ->where('eleccion_id', $eleccionId)
            ->when(!$puestosPermitidos->isEmpty(), function ($q) use ($puestosPermitidos) {
                $q->whereIn('id', $puestosPermitidos->keys()->all());
            })
            ->select([
                'id',
                'dd',
                'mm',
                'zz',
                'pp',
                'municipio',
                'puesto',
                'mesas_total',
            ]);
        $this->applyEleccionScope($puestosQuery, $eleccionId, 'eleccion_puestos');

        foreach ($puestosQuery->get() as $puesto) {
            $total = (int) $puesto->mesas_total;
            if ($total <= 0) {
                continue;
            }

            $remanentes = $total < 10 ? 1 : (int) ceil($total * 0.10);
            for ($i = 1; $i <= $remanentes; $i++) {
                $remanentesRows->push((object) [
                    'mesa_id' => null,
                    'mesa_label' => 'Rem ' . $i,
                    'mesa_sort' => 1000 + $i,
                    'is_remanente' => true,
                    'estado' => 'remanente',
                    'referido_id' => null,
                    'observaciones' => null,
                    'nombre' => null,
                    'municipio' => $puesto->municipio,
                    'puesto' => $puesto->puesto,
                    'dd' => $puesto->dd,
                    'mm' => $puesto->mm,
                    'zz' => $puesto->zz,
                    'pp' => $puesto->pp,
                ]);
            }
        }

        $mesas = $mesasRows
            ->concat($remanentesRows)
            ->sortBy(function ($row) {
                return sprintf('%s-%s-%s-%s-%06d', $row->dd, $row->mm, $row->zz, $row->pp, $row->mesa_sort);
            })
            ->values();

        return view('admin.ani.index', compact('mesas', 'mesasPage'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.ani.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return redirect()->route('admin.ani.index');

    }

    // public function edit($ani)
    // {
    //     $superuser = Seller::where('id' , $ani)->get();

    //     $pdfUrl = null;
    //     if ($superuser->pdf) {
    //         $pdfUrl = Storage::disk('s3')->temporaryUrl(
    //             $superuser->pdf,
    //             now()->addMinutes(10) // enlace válido por 10 minutos
    //         );
    //     }

        
        
        
        
    //     $puestos= Puestos::all();

    //     // dd($puestos);
    //     return view('admin.ani.edit', compact('puestos', 'ani', 'pdfUrl'))->with('superuser', $superuser);
    // }

    public function edit($ani)
    {
        $referido = Referido::findOrFail($ani);
        $persona = Persona::find($referido->persona_id);
        $puesto = EleccionPuesto::find($referido->eleccion_puesto_id);

        $pdfUrl = $referido->cedula_pdf_path
            ? asset('storage/' . $referido->cedula_pdf_path)
            : null;

        $puestos = EleccionPuesto::where('eleccion_id', $referido->eleccion_id)
            ->orderBy('municipio')
            ->orderBy('puesto')
            ->get();

        return view('admin.ani.edit', compact('referido', 'persona', 'puesto', 'puestos', 'pdfUrl'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $ani)
    {
        $referido = Referido::findOrFail($ani);
        $persona = Persona::findOrFail($referido->persona_id);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'cedula' => ['required', 'string', 'max:50', Rule::unique('personas', 'cedula')->ignore($persona->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('personas', 'email')->ignore($persona->id)],
            'telefono' => 'nullable|string|max:50',
            'observacion' => 'nullable|string|max:255',
            'statusani' => 'required|in:0,1',
            'eleccion_puesto_id' => 'required|integer|exists:eleccion_puestos,id',
            'mesa_num' => 'required|integer|min:1',
            'pdf' => 'nullable|file|mimes:pdf|max:2048',
        ]);

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
        $referido->observaciones = $data['observacion'] ?? null;
        $referido->estado = $data['statusani'] === '1' ? 'validado' : 'asignado';
        $referido->save();

        return redirect()->route('admin.ani.index')->with('info', 'Validacion ANI guardada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Referido $ani)
    {
        $ani->delete();
        return redirect()->route('admin.ani.index')->with('info', 'El testigo se elimino con exito');
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

}
