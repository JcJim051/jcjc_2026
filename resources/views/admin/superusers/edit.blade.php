@extends('adminlte::page')

@section('title', 'Admin')

@section('content_header')

    <h1> {{ $puesto->puesto ?? '' }} MESA {{ $superuser->mesa_num }}</h1>


@stop

@section('content')

@if (session('info'))
        <div class="alert alert-success">
            <strong>{{(session('info'))}}</strong>
        </div>
@endif
@if (session('error'))
        <div class="alert alert-danger">
            <strong>{{(session('error'))}}</strong>
        </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @php
            $readonly = $readonly ?? false;
        @endphp
        {!! Form::model($superuser, ['route' => ['admin.superusers.update',$superuser], 'method' => 'PUT', 'enctype' => 'multipart/form-data']) !!}
        @csrf



        <div class="form-group"> {{-- A continuacion se usa laravel collective para crar el formulario --}}
            {!! Form::label("nombre", "Nombre") !!}
            {!! Form::text("nombre", $persona->nombre ?? '', array_merge(["class" => "form-control", 'placeholder' => 'Ingrese su nombre','required' => 'required'], $readonly ? ['readonly' => 'readonly'] : [])) !!}

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
                {!! Form::label("cedula", "Cédula") !!}
                {!! Form::number("cedula", $persona->cedula ?? '', array_merge(["class" => "form-control disabled", 'placeholder' => 'Ingrese su cédula', 'required' => 'required' ], $readonly ? ['readonly' => 'readonly'] : [])) !!}

                @error('cedula')
                    <span class="text-danger">{{$message}}</span>
                @enderror

            </div>

            <div class="form-group">
                {!! Form::label("email", "Email") !!}
                {!! Form::email("email", $persona->email ?? '', array_merge(["class" => "form-control","placeholder" => "Ingrese su email", "required" => "required"], $readonly ? ['readonly' => 'readonly'] : [])) !!}

                @error('email')
                    <span class="text-danger">{{$message}}</span>
                @enderror

            </div>
            
            <div class="card card-outline card-warning">
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-sm-6 ">
                            {!! Form::label("telefono", "Telefono") !!}
                            {!! Form::number("telefono", $persona->telefono ?? '', array_merge(["class" => "form-control disabled", 'placeholder' => 'Ingrese su telefono', 'required' => 'required'], $readonly ? ['readonly' => 'readonly'] : [])) !!}
            
                            @error('telefono')
                                <span class="text-danger">{{$message}}</span>
                            @enderror    
                        </div>
                        <div class="col-sm-5 col-xs-12">
                            <label for=""> Puesto de votación </label><br>
                            <select class="js-example-basic-single form-control" name="eleccion_puesto_id" style="width: 100%;" required {{ $readonly ? 'disabled' : '' }}>
                                @foreach ($puestos as $p)
                                    <option value="{{ $p->id }}" {{ $superuser->eleccion_puesto_id == $p->id ? 'selected' : '' }}>
                                        {{ $p->municipio }} - {{ $p->puesto }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-2 col-xs-12">
                            <label for="">Mesa</label>
                            <input type="number" name="mesa_num" class="form-control" value="{{ $superuser->mesa_num }}" min="1" required {{ $readonly ? 'readonly' : '' }}>
                        </div>
                        {{-- <div class="form-group col-sm-6">
                            {!! Form::label("banco", "Banco") !!}
                            {!! Form::select("banco",[ null => 'Seleccione un banco','Otro' => 'Otro', 'Nequi' => 'Nequi', 'Daviplata' => 'Daviplata',  'Ahorro_a_la_mano' => 'Ahorro a la mano' ], null, ["class" => "form-control disabled", 'required' => 'required' ]) !!}
            
                            @error('telefono')
                                <span class="text-danger">{{$message}}</span>
                            @enderror    
                        </div> --}}
                    </div>
        
                </div>
            </div>
            


            <div class="row">
                <div class="col-sm-5 col-xs-12">
                    {!! Form::label("pdf", "Pdf Cédula (max 2 mb)") !!} <br>
                    @php
                        $pdfAttrs = [
                            "class" => "form-control",
                            "accept" => ".pdf",
                        ];
                        if (!$superuser->cedula_pdf_path) {
                            $pdfAttrs['required'] = 'required';
                        }
                        if ($readonly) {
                            $pdfAttrs['disabled'] = 'disabled';
                        }
                    @endphp
                    {!! Form::file("pdf", $pdfAttrs) !!}
            
                    @error('pdf')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            
                <div class="col-sm-2 col-xs-12">
                    <label for="">Documento cargado</label><br>
                    @if ($superuser->cedula_pdf_path == null)
                        Sin cargar
                    @else
                        <a target="_blank" rel="noopener noreferrer" href="{{ $pdfUrl }}">Ver adjunto</a>
                    @endif
                </div>
            </div>
            

            




            <input type="text" value="{{Auth::user()->name}}" id="modificadopor" name="modificadopor" hidden />




            <div class="form-group">
                <label>Estado actual</label>
                <input type="text" class="form-control" value="{{ $superuser->estado }}" readonly>
            </div>

            @if ($readonly)
                <div class="alert alert-info">
                    Este testigo está validado o en un estado superior. Solo lectura.
                </div>
            @else
                @can('no-editar')
                    {!! Form::submit('Guardar', ['class' => 'btn btn-info']) !!}
                @endcan
            @endif





        {!! Form::close() !!}
    </div>
</div>
@stop
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

@endsection
@section('js')
    <script> console.log('de tu mano señor!'); </script>
    <script src="https://code.jquery.com/jquery-3.7.0.js" integrity="sha256-JlqSTELeR4TLqP0OG9dxM7yDPqX1ox/HfgiSLBj8+kM=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.js-example-basic-single').select2();
        });
    </script>

    <script>
        document.getElementById('pdfInput').addEventListener('change', function () {
            const file = this.files[0];
            const maxSize = 2 * 1024 * 1024; // 2 MB
        
            if (!file) return;
        
            // Validar tipo
            if (file.type !== 'application/pdf') {
                alert('El archivo debe estar en formato PDF.');
                this.value = '';
                return;
            }
        
            // Validar tamaño
            if (file.size > maxSize) {
                alert('El archivo no puede pesar más de 2 MB.');
                this.value = '';
                return;
            }
        });
    </script>
    
    
@endsection




