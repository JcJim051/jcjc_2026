<?php

namespace App\Http\Controllers\Admin;

use Image;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\Seller;
use Illuminate\Http\Request;
use App\Traits\Compartimentacion;
use App\Traits\EleccionScope;

class TellerController extends Controller
{
    use Compartimentacion, EleccionScope;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    // public function index()
    // {
    //     $role = auth()->user()->role;
    //     $escrutador = auth()->user()->codzon;
    //     $ruta = auth()->user()->codzon;
    //     $crisis = auth()->user()->codzon;
    //     $coordinador = auth()->user()->codpuesto;
    //     $municipio = auth()->user()->mun;




    //             if ($role == 1) {
    //                if ($municipio == 1) {
    //                     $sellers = Seller::where('mesa','<>', 'Rem')->where('codmun' ,'=', '001')->get();
    //                } else {
    //                 if ($municipio == 0) {
    //                     $sellers = Seller::where('mesa','<>', 'Rem')->where('codmun' ,'<>', '001')->where('cod_ruta' , $ruta)->get();
    //                    } else {
    //                     $sellers = Seller::where('mesa','<>', 'Rem')->get();
    //                    }    
    //                }
                       
    //             } else {
    //                 if ($role == 2) {
    //                     $sellers = Seller::where('mesa','<>', 'Rem')->where('codescru' , $escrutador)->get();
    //                 } else {

    //                     if ($role == 3) {
    //                         $puestos = array_filter(array_map('trim', explode(',', $coordinador)));

    //                         $sellers = Seller::where('mesa', '<>', 'Rem')
    //                             ->whereIn('codcor', $puestos)
    //                             ->get();
    //                     } else {
    //                             if ($role == 4) {
    //                                 $sellers = Seller::where('mesa','<>', 'Rem')->get();
    //                             } else {
    //                                 if ($role == 7) {
    //                                     $sellers = Seller::where('codmesa_crisis','=',$crisis)->get();
    //                                 } else {
                                         
    //                                 }
    //                             }
    //                          }


    //                 }
    //              }

               

    //     return view('admin.tellers.index', compact('sellers'));

    //     // $sellers = Seller::all();

    //     // return view('admin.tellers.index', compact('sellers'));
    // }
    public function index()
    {
        $user = auth()->user();
        $sellers = $this->filtrarSellersPorUsuario($user);
    
        return view('admin.tellers.index', compact('sellers'));
    }
    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Seller $teller)
    {
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));
        $candidatosE14 = Candidato::query()
            ->where('activo', 1)
            ->whereNotNull('campo_e14')
            ->when($eleccionId, fn ($q) => $q->where('eleccion_id', $eleccionId))
            ->orderBy('campo_e14')
            ->orderBy('codigo')
            ->get();

        return view('admin.tellers.edit', compact('teller', 'candidatosE14'));
    }

    public function show(Seller $teller)
    {   
           return view('admin.tellers.edit1', compact('teller'));
    }

    public function edit1(Seller $teller)
    {   
           return view('admin.tellers.edit1', compact('teller'));
    }
    public function edit2(Seller $teller)
    {       
           $foto = $teller;
        
            return view('admin.tellers.edit2', compact('foto'));
    }
    public function edit3(Seller $teller)
    {     $foto = $teller;
           return view('admin.tellers.edit3', compact('foto'));
    }

    // public function foto2(Seller $teller)
    // {   
    //        return view('admin.tellers.edit2', compact('teller'));
    // }

    // public function foto3(Seller $teller)
    // {   
    //        return view('admin.tellers.edit3', compact('teller'));
    // }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Seller $teller)
    {
            $teller->update($request->all());
            $role = auth()->user()->role;
            
            if ($role == 7) {
                return redirect()->route('admin.pmu.index', $teller)->with('info', 'Reporte de votos se actualizó con Éxito');
            } else {
                return redirect()->route('admin.tellers.index', $teller)->with('info', 'Reporte de votos se actualizó con Éxito');
            }

        


        
    }

    public function fotos(Request $request , Seller $teller  )
    {   
        
        $id = $request->id;
        
        if ($request->input('e14_resized') !== null) {
            // Obtén la imagen redimensionada del campo oculto
            $e14Resized = $request->input('e14_resized');
        
            // Decodifica la imagen redimensionada desde formato base64
            $decodedImage = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $e14Resized));
        
            // Genera un nombre único para el archivo JPEG
            $uniqueFilename = uniqid() . '_' . $request->user()->id . '.jpg';
        
            // Ruta donde deseas guardar la imagen redimensionada
            $path = 'E14-images/' . $uniqueFilename;
        
            // Guarda la imagen redimensionada en la ubicación deseada
            Storage::put($path, $decodedImage);
        
            // Actualiza el campo 'e14' en el modelo Seller
            $teller = Seller::where('id', $id)->first();
            $teller->e14 = $path;
            $teller->save();
        }
        if ($request->input('e14_2_resized') !== null) {
            // Obtén la imagen redimensionada del campo oculto
            $e14_2Resized = $request->input('e14_2_resized');
        
            // Decodifica la imagen redimensionada desde formato base64
            $decodedImage = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $e14_2Resized));
        
            // Genera un nombre único para el archivo JPEG
            $uniqueFilename = uniqid() . '_' . $request->user()->id . '.jpg';
        
            // Ruta donde deseas guardar la imagen redimensionada
            $path = 'e14-images/' . $uniqueFilename;
        
            // Guarda la imagen redimensionada en la ubicación deseada
            Storage::put($path, $decodedImage);
        
            // Actualiza el campo 'e14_2' en el modelo Seller
            $teller = Seller::where('id', $id)->first();
            $teller->e14_2 = $path;
            $teller->save();
        }

        if ($request->input('fotorec_resized') !== null) {
            // Obtén la imagen redimensionada del campo oculto
            $fotorecResized = $request->input('fotorec_resized');
        
            // Decodifica la imagen redimensionada desde formato base64
            $decodedImage = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $fotorecResized));
        
            // Genera un nombre único para el archivo JPEG
            $uniqueFilename = uniqid() . '_' . $request->user()->id . '.jpg';
        
            // Ruta donde deseas guardar la imagen redimensionada
            $path = 'fotorec-images/' . $uniqueFilename;
        
            // Guarda la imagen redimensionada en la ubicación deseada
            Storage::put($path, $decodedImage);
        
            // Actualiza el campo 'fotorec' en el modelo Seller
            $teller = Seller::where('id', $id)->first();
            $teller->fotorec = $path;
            $teller->save();
        }
        
        $foto = $id;
       
        return redirect()->route('admin.fotos.edit',compact('foto'))->with('info', 'Primera cara del E14 enviada con Éxito');
    }

}
