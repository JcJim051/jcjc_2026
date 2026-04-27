<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Resultados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\EleccionScope;

class TransmisionController extends Controller
{
    use EleccionScope;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {   
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
        $base = DB::table('sellers');
        $this->applyEleccionScopeSeller($base, $eleccionId, 'sellers');

        $avance_mun = (clone $base)
                    ->select('municipio', 
                            DB::raw('count(*) as total_mesas'), 
                            DB::raw('SUM(CASE WHEN gob1 IS NOT NULL THEN 1 ELSE 0 END) as mesas_ok'),
                            DB::raw('SUM(CASE WHEN e14 IS NOT NULL AND e14_2 IS NOT NULL THEN 1 ELSE 0 END) as fotos_ok')
                            )
                    
                    ->groupBy('municipio')
                    ->get();
        $avance_zonas = (clone $base)
                            ->select('codescru', 
                                    DB::raw('count(*) as total_mesas'), 
                                    DB::raw('SUM(CASE WHEN gob1 IS NOT NULL THEN 1 ELSE 0 END) as mesas_ok'),
                                    DB::raw('SUM(CASE WHEN e14 IS NOT NULL AND e14_2 IS NOT NULL THEN 1 ELSE 0 END) as fotos_ok')
                                    )
                            ->where('municipio', 'VILLAVICENCIO')
                            ->groupBy('codescru')
                            ->get();

        $avance_pareto = (clone $base)
                            ->select('municipio', 
                                DB::raw('count(*) as total_mesas'), 
                                DB::raw('SUM(CASE WHEN gob1 IS NOT NULL THEN 1 ELSE 0 END) as mesas_ok'),
                                DB::raw('SUM(CASE WHEN e14 IS NOT NULL AND e14_2 IS NOT NULL THEN 1 ELSE 0 END) as fotos_ok'))
                            ->whereIn('municipio', ['VILLAVICENCIO', 'ACACIAS', 'PUERTO GAITAN', 'GRANADA'])
                            ->groupBy('municipio')
                            ->get();

                $tm = (clone $base)
                            ->count('mesa');
                $tmi= (clone $base)
                            ->where('gob1', '<>' , null)
                            ->count(); 

                $recl = (clone $base)                
                        ->where('reclamacion','=','1')              
                        ->count();
        //     Total reclamaciones con foto
                
                $trf= (clone $base)
                            ->where('reclamacion' , 1)
                            ->where('fotorec', '<>' , null)
                            ->count();                             
          
             //dd($avance_mun);
        return view('admin.transmision.index',compact('avance_mun', 'avance_zonas', 'avance_pareto','tm','tmi', 'trf', 'recl'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    
//     public function getTransmision() {
//         $tm = DB::table('sellers')
//                 ->count('mesa');
//         $tmi= DB::table('sellers')
//                 ->where('gob1', '<>' , '')
//                 ->count();

//         $tmi2= DB::table('sellers')
//                 ->where('gob2', '<>' , '')
//                 ->count();
//         $tmi3= DB::table('sellers')
//                 ->where('gob3', '<>' , '')
//                 ->count();

//         $tv1 = DB::table('sellers')
//                 ->select("gob1")
//                 ->sum('gob1');

//         $tv2 = DB::table('sellers')
//                 ->select("gob2")
//                 ->sum('gob2');
//         $tv3 = DB::table('sellers')
//                 ->select("gob3")
//                 ->sum('gob3');

//         $tr =  DB::table('sellers')
//                 ->select("recuperados")
//                 ->sum('recuperados');

//         $candidatos = DB::table('sellers')
//                 ->where('mesa','<>','Rem')
//                 ->select( DB::raw('sum(Gob1) as Rafaela'), DB::raw('sum(Gob2) as Marcela'), DB::raw('sum(Gob3) as Dario'), DB::raw('sum(Gob4) as Wilmar'), DB::raw('sum(gob5) as libreros'), DB::raw('sum(Gob6) as Barreto'), DB::raw('sum(Gob7) as Bairon'), DB::raw('sum(Gob8) as Antonio'), DB::raw('sum(Gob9) as Florentino'), DB::raw('sum(Gob10) as Eudoro'), DB::raw('sum(Gob11) as Jose'))
                
//                 ->get();

//         $data = DB::table('sellers')
//                 ->where('codmun','=','001')
//                 ->where('mesa','<>','Rem')
//                 ->select('codescru', DB::raw('sum(recuperados) as T'))
//                 ->groupBy('codescru')
//                 ->orderBy('codescru', 'asc')
//                 ->get();
        
//         $dt = DB::table('sellers')
//                 ->where('codmun','=','001')
//                 ->where('mesa','<>','Rem')
//                 ->select('codzon', DB::raw('sum(gob1) as T'))
//                 ->groupBy('codzon')
//                 ->orderBy('codzon', 'asc')
//                 ->get();
//         // Votos por municipios
//         $labelmun =  DB::table('sellers')
//         ->select('municipio', DB::raw('sum(gob1) as T'))
//         ->where('municipio','<>','VILLAVICENCIO')
//         ->groupBy('municipio')
//         ->get();

      



        

//         // dd($datos);

//         // return view('admin.resultados.index' , compact('tv1','tv2','tv3', 'tm', 'tmi', 'tmi2','tmi3','tr','data', 'dat', 'd', 'dt', 'recl' ,'lablemun','okmun','nookmun'));
//         return response()->json([
//                 'candidatos'=> $candidatos,
//                 'tv1' => $tv1,
//                 'tv2'=> $tv2,
//                 'tv3'=> $tv3,
//                 'tm'=> $tm,
//                 'tmi'=> $tmi,
//                 'tmi2'=> $tmi2,
//                 'tmi3'=> $tmi3,
//                 'tr'=> $tr,
//                 'data'=> $data,
                
//                 'dt'=> $dt,
//                 'recl'=> $recl,
//                 'labelmun'=> $labelmun,
                
//         ]);
        

//     }


}
