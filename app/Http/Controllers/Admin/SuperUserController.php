<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referido;
use App\Models\Persona;
use App\Models\EleccionPuesto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Traits\EleccionScope;


class SuperUserController extends Controller
{
    use EleccionScope;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //El inicial
    // public function index()
    // {

    //     $role = auth()->user()->role;
    //     $escrutador = auth()->user()->codzon;
    //     $ruta = auth()->user()->codzon;
    //     $coordinador = auth()->user()->codpuesto;
    //     $municipio = auth()->user()->mun;
    //     $idUser = auth()->id();



    //             if ($role == 1) {
    //                 // 1 = villa-{-.…o
    //                     if ($idUser == 1 || $municipio == 999) {
    //                         // Muestra todo
    //                         $sellers = Seller::get();
    //                     } else {
    //                         // Muestra solo los de su municipio
    //                         $municipios = explode(',', $municipio);
    //                         $sellers = Seller::whereIn('codmun', $municipios)->get();
    //                     }
    //             } else {
    //                 if ($role == 2) {
    //                     $sellers = Seller::where('codescru' , $escrutador)->get();
    //                 } else {

    //                     if ($role == 3) {
    //                         $puestos = array_filter(explode(',', $coordinador));
    //                         $sellers = Seller::whereIn('codcor', $puestos)->get();
                           
    //                     } else {
    //                             if ($role == 4 or $role == 5) {
    //                                 $sellers = Seller::all();
    //                             } else {
    //                                 $sellers = [];
    //                             }

    //                          }


    //                 }
    //              }


    //     return view('admin.superusers.index', compact('sellers'));
    // }
        // EL AJUSTADO POR CANDIDATO
    // public function index()
    // {
    //     $user = auth()->user();
    //     $role = $user->role;
    //     $idUser = $user->id;
    
    //     // Convertimos campos guardados como texto a arrays
    //     $userCandidatos = $user->candidatos ? explode(',', $user->candidatos) : [];
    //     $userMunicipios = $user->mun ? explode(',', $user->mun) : [];
    //     $userPuestos = $user->codpuesto ? explode(',', $user->codpuesto) : [];
    //     $escrutador = $user->codzon;
    
    //     $sellers = Seller::query();
    
    //     if ($role == 1) { // ADMIN
    //         if ($idUser == 1 || in_array('999', $userMunicipios)) {
    //             // Muestra todo
    //             $sellers = $sellers->get();
    //         } else {
    //             // Filtra por municipios
    //             $sellers = $sellers->whereIn('codmun', $userMunicipios);
    
    //             // Filtra por candidatos si tiene asignados
    //             if (!empty($userCandidatos)) {
    //                 $sellers = $sellers->whereIn('candidato', $userCandidatos);
    //             }
    
    //             $sellers = $sellers->get();
    //         }
    //     } elseif ($role == 2) { // ESCRUTADOR
    //         $sellers = $sellers->where('codescru', $escrutador)->get();
    //     } elseif ($role == 3) { // COORDINADOR
    //         $sellers = $sellers->whereIn('codcor', $userPuestos);
    
    //         // Filtra por candidatos
    //         if (!empty($userCandidatos)) {
    //             $sellers = $sellers->whereIn('candidato', $userCandidatos);
    //         }
    
    //         $sellers = $sellers->get();
    //     } elseif ($role == 4 || $role == 5) { // Otros roles
    //         $sellers = $sellers->get();
    //     } else {
    //         $sellers = collect();
    //     }
    
    //     return view('admin.superusers.index', compact('sellers'));
    // }
    public function index()
    {
        $user = auth()->user();
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
        if (!$eleccionId) {
            return view('admin.superusers.index', ['mesas' => collect()]);
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
                'p.nombre',
                'p.telefono',
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
                'nombre' => $row->nombre,
                'telefono' => $row->telefono,
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
                    'nombre' => null,
                    'telefono' => null,
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

        return view('admin.superusers.index', compact('mesas', 'mesasPage'));
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.superusers.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return redirect()->route('admin.superusers.index');

    }

    public function edit(Referido $superuser)
    {
        $persona = Persona::find($superuser->persona_id);
        $puesto = EleccionPuesto::find($superuser->eleccion_puesto_id);
        $puestos = EleccionPuesto::where('eleccion_id', $superuser->eleccion_id)
            ->orderBy('municipio')
            ->orderBy('puesto')
            ->get();

        $pdfUrl = $superuser->cedula_pdf_path
            ? asset('storage/' . $superuser->cedula_pdf_path)
            : null;

        $readonly = in_array($superuser->estado, ['validado', 'postulado', 'acreditado'], true);

        return view('admin.superusers.edit', compact('superuser', 'persona', 'puesto', 'puestos', 'pdfUrl', 'readonly'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // public function update(Request $request, Seller $superuser)
    // {
        
    
        
    //    if ($superuser->pdf == null) {
    //         $request->validate([
    //             'email' => [
    //                 'nullable',
    //                 Rule::unique('sellers')->ignore($superuser->id)->whereNotNull('email'),
    //             ],
    //             'cedula' => [
    //                 'nullable',
    //                 Rule::unique('sellers')->ignore($superuser->id)->whereNotNull('cedula'),
    //             ],
    //             'pdf' => 'required',
                
                         
    //         ]);
    //     } else {
    //             $request->validate([
    //             'email' => [
    //                 'nullable',
    //                 Rule::unique('sellers')->ignore($superuser->id)->whereNotNull('email'),
    //             ],
    //             'cedula' => [
    //                 'nullable',
    //                 Rule::unique('sellers')->ignore($superuser->id)->whereNotNull('cedula'),
    //             ],
                     
    //         ]);
    //     }
        
    //     if($request->hasfile('pdf')){

    //     $superuser['pdf']= $request->file('pdf')->getClientOriginalName();
    //     $request->file('pdf');

    //     $superuser['pdf']= $request->file('pdf')->store('/cedulas-pdf');


    //     }

    //         $superuser->update($request->all());

    //         if ($request->statusani == null  & $request->statusrec == null & $request->statusasistencia == null & $request->modificadopor_pmu == null)  {
    //             return redirect()->route('admin.superusers.index', $superuser)->with('info', ' Testigo actualizado con exito');
    //         } else {
    //             if ($request->statusrec == null & $request->statusasistencia == null  & $request->modificadopor_pmu == null) {
    //                 return redirect()->route('admin.ani.index', $superuser)->with('info', ' Validación Ani Guardada con Exito');
    //             } else {
    //                 if ($request->statusasistencia <> null  & $request->modificadopor_pmu == null) {
    //                     return redirect()->route('admin.posesion.index', $superuser)->with('info', 'Reporte de asistencia Guardado con Exito');
    //                 } else {
    //                     if ($request->modificadopor_pmu <> null) {
    //                         return redirect()->route('admin.pmu.index', $superuser)->with('info', 'Correccion mesa de crisis, reportada con exito');
    //                     } else {
    //                         return redirect()->route('admin.revision.index', $superuser)->with('info', 'Revisión E24 Guardada con Exito');
    //                     }
    //                 }
    //             }
    //         }
            

       
    // }

 

    public function update(Request $request, Referido $superuser)
    {
        if (in_array($superuser->estado, ['validado', 'postulado', 'acreditado'], true)) {
            return redirect()
                ->route('admin.superusers.edit', $superuser)
                ->with('error', 'Este testigo ya está validado o más avanzado. Solo lectura.');
        }

        $persona = Persona::findOrFail($superuser->persona_id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'cedula' => ['required', 'string', 'max:50', Rule::unique('personas', 'cedula')->ignore($persona->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('personas', 'email')->ignore($persona->id)],
            'telefono' => 'nullable|string|max:50',
            'eleccion_puesto_id' => 'required|integer|exists:eleccion_puestos,id',
            'mesa_num' => 'required|integer|min:1',
            'pdf' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        $persona->nombre = $validated['nombre'];
        $persona->nombre_original = $validated['nombre'];
        $persona->nombre_normalizado = strtolower(Str::ascii($validated['nombre']));
        $persona->cedula = preg_replace('/[^0-9]/', '', (string) $validated['cedula']);
        $persona->email = $validated['email'];
        $persona->telefono = $validated['telefono'] ?? null;
        $persona->save();

        if ($request->hasFile('pdf')) {
            $path = $request->file('pdf')->store('cedulas', 'public');
            $superuser->cedula_pdf_path = $path;
        }

        $superuser->eleccion_puesto_id = (int) $validated['eleccion_puesto_id'];
        $superuser->mesa_num = (int) $validated['mesa_num'];
        $superuser->save();

        return redirect()
            ->route('admin.superusers.index')
            ->with('info', 'Testigo actualizado con éxito');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Referido $superuser)
    {

        $superuser->delete();
        return redirect()->route('admin.superusers.index')->with('info', 'El testigo se elimino con exito');
    }


}
