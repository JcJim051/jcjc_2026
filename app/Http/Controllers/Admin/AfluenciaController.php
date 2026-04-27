<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resultados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Seller;
use App\Traits\EleccionScope;

class AfluenciaController extends Controller
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

            $avg1 = DB::table('sellers')
                    ->select('Codcor', DB::raw('AVG(reporte_1) as promedio'))
                    ->groupBy('Codcor');
            $this->applyEleccionScopeSeller($avg1, $eleccionId, 'sellers');

            $primeraQuery = DB::table('sellers as s1')
                    ->joinSub($avg1, 's2', function ($join) {
                        $join->on('s1.Codcor', '=', 's2.Codcor');
                    })
                    ->select('s1.Municipio', 's1.Puesto', 's1.Mesa','s1.reporte_1 as Reporte', DB::raw('s2.promedio as Promedio'))
                    ->whereRaw('s1.reporte_1 > s2.promedio * 1.3');
            $this->applyEleccionScopeSeller($primeraQuery, $eleccionId, 's1');
            $primera = $primeraQuery->get()->toArray();

            $avg2 = DB::table('sellers')
                    ->select('Codcor', DB::raw('AVG(reporte_2) as promedio'))
                    ->groupBy('Codcor');
            $this->applyEleccionScopeSeller($avg2, $eleccionId, 'sellers');

            $segundoQuery = DB::table('sellers as s1')
                    ->joinSub($avg2, 's2', function ($join) {
                        $join->on('s1.Codcor', '=', 's2.Codcor');
                    })
                    ->select('s1.Municipio', 's1.Puesto','s1.Mesa', 's1.reporte_2 as Reporte', DB::raw('s2.promedio as Promedio'))
                    ->whereRaw('s1.reporte_2 > s2.promedio * 1.3');
            $this->applyEleccionScopeSeller($segundoQuery, $eleccionId, 's1');
            $segundo = $segundoQuery->get()->toArray();

            $avg3 = DB::table('sellers')
                    ->select('Codcor', DB::raw('AVG(reporte_3) as promedio'))
                    ->groupBy('Codcor');
            $this->applyEleccionScopeSeller($avg3, $eleccionId, 'sellers');

            $terceroQuery = DB::table('sellers as s1')
                    ->joinSub($avg3, 's2', function ($join) {
                        $join->on('s1.Codcor', '=', 's2.Codcor');
                    })
                    ->select('s1.Municipio', 's1.Puesto', 's1.Mesa','s1.reporte_3 as Reporte', DB::raw('s2.promedio as Promedio'))
                    ->whereRaw('s1.reporte_3 > s2.promedio * 1.3');
            $this->applyEleccionScopeSeller($terceroQuery, $eleccionId, 's1');
            $tercero = $terceroQuery->get()->toArray();
                
            
            //dd($promedio);
   
            return view('admin.afluencia.index', compact('primera', 'segundo','tercero'));

    }
    public function getAfluencia()
    {
            $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
            $base = DB::table('sellers');
            $this->applyEleccionScopeSeller($base, $eleccionId, 'sellers');

            $tm = (clone $base)
                     ->where('mesa', '<>' , 'Rem')
                     ->count('mesa');
            $tmi_1= (clone $base)
                    ->where('reporte_1', '<>' , '')
                    ->count();
            $tmi_2= (clone $base)
                    ->where('reporte_2', '<>' , '')
                    ->count();
            $tmi_3= (clone $base)
                    ->where('reporte_2', '<>' , '')
                    ->count();

           
            $tv1 = (clone $base)
                    ->select("reporte_1")
                    ->sum('reporte_1');

            $tv2 = (clone $base)
                    ->select("reporte_2")
                    ->sum('reporte_2');
            $tv3 = (clone $base)
                    ->select("reporte_3")
                    ->sum('reporte_3');

           

            $tr=1;

           
                $dt =  (clone $base)
                        ->where('codmun','=','001')
                        ->where('mesa','<>','Rem')
                        ->select('codzon', DB::raw('sum(reporte_1) as T'),DB::raw('sum(reporte_2) as F'),DB::raw('sum(reporte_3) as W'))
                        ->groupBy('codzon')
                        ->orderBy('codzon', 'asc')
                        ->get();

               




            // Votantes por municipios


            $labelmun =  (clone $base)
                        ->select('municipio', DB::raw('sum(reporte_1) as T'),DB::raw('sum(reporte_2) as F'), DB::raw('sum(reporte_3) as W'))
                        ->where('municipio','<>','VILLAVICENCIO')
                        ->groupBy('municipio')
                        ->get();

            // dd($datos);
            return response()->json([
                'dt'=> $dt,
                'labelmun' => $labelmun,
                'tm' => $tm,
                'tmi_1' => $tmi_1,
                'tmi_2' => $tmi_2,
                'tmi_3' => $tmi_3,
                'tv1' => $tv1,
                'tv2' => $tv2,
                'tv3' => $tv3,
        ]);
        
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
}
