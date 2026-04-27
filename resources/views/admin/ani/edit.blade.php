@extends('adminlte::page')

@section('title', 'Admin')

@section('content_header')

        <h1> Validacion Ani - {{ $puesto->puesto ?? '' }} MESA {{ $referido->mesa_num }}</h1>
       


    <style>
          #pdf-viewer {
            width: 100%;
            height: 505px;
        }
    </style>
@stop

@section('content')

@if (session('info'))
        <div class="alert alert-success">
            <strong>{{(session('info'))}}</strong>
        </div>
@endif

        <div class="container" style="">
            <div class="card card-outline card-warning">
                <div class="card-body">
                    <form action="{{ route('admin.ani.update', $referido->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                            <div class="form-group">
                                <div class="row">
                                    <div class="col-4">
                                        {!! Form::label("statusani", "Proceso de Validacion") !!}
                                        
                                    </div>
                                    <div class="col-6">
                                        
                                
                                        {!! Form::select(
                                            'observacion',
                                            [    '' => 'Seleccione',
                                                'Validado' => 'Validado',
                                                'Llamada 1' => 'Llamada 1',
                                                'Llamada 2' => 'Llamada 2',
                                                'Llamada 3' => 'Llamada 3',
                                                'Cambiar No 101' => 'Cambiar No 101',
                                            ],
                                            $referido->observaciones,
                                            ['class' => 'form-control']
                                        ) !!}
                
                                    </div>
                                    <div class="col-2">
                                        {!! Form::select("statusani",[ 0 => 'Pendiente', 1 => 'Listo' ], $referido->estado === 'validado' ? 1 : 0, ["class" => "form-control"]) !!}
                
                                    </div>
                                </div>
                            
            
                                    @error('Estado')
                                        <span class="text-danger">{{$message}}</span>
                                    @enderror
                    
                            
                    </div>
                </div>
            </div>
            
            <div class="row">
                
                <div class="col-6">
                    <div class="card ">
                        <div class="card-body">
                           
                           
                            <div class="form-group"> {{-- A continuacion se usa laravel collective para crar el formulario --}}
                                {!! Form::label("nombre", "Nombre") !!}
                                {!! Form::text("nombre", $persona->nombre ?? '', ["class" => "form-control", 'placeholder' => 'Ingrese su nombre']) !!}
            
                                @error('nombre')
                                    <span class="text-danger">{{$message}}</span>
                                @enderror
            
                            </div>
            
                            {{-- Aca se crea el campo para el Slug y se conecta luego con el plugin JQuery y se pone en modo solo lectura --}}
                            {{-- <div class="form-group">
                                {!! Form::label("slug", "Slug") !!}
                                {!! Form::text("slug", null, ["class" => "form-control disabled", 'placeholder' => 'Slug de nombre', 'readonly']) !!}
            
                                @error('slug')
                                    <span class="text-danger">{{$message}}</span>
                                @enderror
            
                            </div> --}}
            
                                <div class="form-group">
                                    {!! Form::label("cedula", "Cedula") !!}
                                    {!! Form::text("cedula", $persona->cedula ?? '', ["class" => "form-control disabled", 'placeholder' => 'Ingrese su cedula']) !!}
            
                                    @error('cedula')
                                        <span class="text-danger">{{$message}}</span>
                                    @enderror
            
                                </div>
            
                                <div class="form-group">
                                    {!! Form::label("email", "Email") !!}
                                    {!! Form::text("email", $persona->email ?? '', ["class" => "form-control disabled", 'placeholder' => 'Ingrese su email']) !!}
            
                                    @error('email')
                                        <span class="text-danger">{{$message}}</span>
                                    @enderror
            
                                </div>
            
                                <div class="form-group">
                                    {!! Form::label("telefono", "Telefono") !!}
                                    {!! Form::text("telefono", $persona->telefono ?? '', ["class" => "form-control disabled", 'placeholder' => 'Ingrese su telefono']) !!}
            
                                    @error('telefono')
                                        <span class="text-danger">{{$message}}</span>
                                    @enderror
            
                                </div>

                              
            
            
                                <div class="row" >
                                    <div class="col-9">
                                        <label for=""> Puesto de votacion </label><br>
                                        <select class="form-control js-example-basic-single" name="eleccion_puesto_id" style="width: 80%;">
                                            @foreach ($puestos as $p)
                                                <option value="{{ $p->id }}" {{ $referido->eleccion_puesto_id == $p->id ? 'selected' : '' }}>
                                                    {{ $p->municipio }} - {{ $p->puesto }}
                                                </option>
                                            @endforeach
                                        
                                        
                                    </select>
                                    </div>
                                
                                    <div class="col-3">
                                        <label for=""> Mesa </label><br>
                                        <input type="number" name="mesa_num" class="form-control" value="{{ $referido->mesa_num }}" min="1" required>
                                    </div>
            
                                
            
            
                                </div>

                                <div class="row" style="margin-top: 15px;">
                                    <div class="col-9">
                                        <label for=""> PDF Cédula (máx 2 MB) </label>
                                        <input type="file" name="pdf" class="form-control" accept=".pdf">
                                    </div>
                                </div>
            
            
                                
                                        <br>
                                        <button type="submit" class="btn btn-info">Guardar Validacion</button>
                                    
            
            
            
            
            
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div id="pdf-viewer" style="height:600px;">
                        @if($pdfUrl)
                            <iframe src="{{ $pdfUrl }}" width="100%" height="100%" frameborder="0"></iframe>
                        @else
                            <p>No hay PDF cargado.</p>
                        @endif
                    </div>
                </div>
            </div>

        </div>
        
       
@stop
@section('js')
    <script> console.log('de tu mano señor!'); </script>
    <script>
        $(document).ready(function() {
            $('.js-example-basic-single').select2();
        });
    </SCript>
@endsection





