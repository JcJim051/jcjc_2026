@extends('adminlte::page')

@section('title', 'Validacion ANI')

@section('content_header')
    <h1>Validacion ANI - {{ $puesto->puesto ?? '' }} MESA {{ $referido->mesa_num }}</h1>
    <style>
        #pdf-viewer { width: 100%; height: 600px; }
    </style>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="container-fluid">
        <form action="{{ route('admin.validacion_ani.update', $referido->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card card-outline card-warning">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label for="historial_observaciones">Historial (solo lectura)</label>
                            <textarea id="historial_observaciones" class="form-control" rows="5" readonly>{{ $referido->observaciones }}</textarea>
                            <small class="text-muted d-block mt-1">Se actualiza automáticamente con el usuario y la fecha.</small>
                        </div>
                        <div class="col-md-5">
                            <label for="nueva_observacion">Nueva observación</label>
                            <textarea name="nueva_observacion" id="nueva_observacion" class="form-control" rows="5" placeholder="Escribe una nueva observación...">{{ old('nueva_observacion') }}</textarea>
                            <small class="text-muted d-block mt-1">Solo se agrega al historial, no reemplaza lo anterior.</small>
                        </div>
                        <div class="col-md-3">
                            <label for="estado">Estado</label>
                            <select name="estado" id="estado" class="form-control" required>
                                <option value="asignado" {{ old('estado', $referido->estado) === 'asignado' ? 'selected' : '' }}>Asignado</option>
                                <option value="validado" {{ old('estado', $referido->estado) === 'validado' ? 'selected' : '' }}>Validado</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="nombre">Nombre</label>
                                <input type="text" class="form-control" name="nombre" id="nombre" value="{{ old('nombre', $persona->nombre ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="cedula">Cedula</label>
                                <input type="text" class="form-control" name="cedula" id="cedula" value="{{ old('cedula', $persona->cedula ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" name="email" id="email" value="{{ old('email', $persona->email ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="telefono">Telefono</label>
                                <input type="text" class="form-control" name="telefono" id="telefono" value="{{ old('telefono', $persona->telefono ?? '') }}">
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <label for="eleccion_puesto_id">Puesto de votacion</label>
                                    <select class="js-example-basic-single form-control" name="eleccion_puesto_id" id="eleccion_puesto_id" style="width: 100%;" required>
                                        @foreach ($puestos as $p)
                                            <option value="{{ $p->id }}" {{ (int) old('eleccion_puesto_id', $referido->eleccion_puesto_id) === (int) $p->id ? 'selected' : '' }}>
                                                {{ $p->municipio }} - {{ $p->puesto }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="mesa_num">Mesa</label>
                                    <input type="number" class="form-control" name="mesa_num" id="mesa_num" min="1" value="{{ old('mesa_num', $referido->mesa_num) }}" required>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-9">
                                    <label for="pdf">PDF Cédula (máx 2 MB)</label>
                                    <input type="file" class="form-control" name="pdf" id="pdf" accept=".pdf">
                                </div>
                                <div class="col-md-3">
                                    <label>Documento</label><br>
                                    @if ($pdfUrl)
                                        <a href="{{ $pdfUrl }}" target="_blank" rel="noopener noreferrer">Ver adjunto</a>
                                    @else
                                        <span class="text-muted">Sin cargar</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-info">Guardar Validacion</button>
                                <a href="{{ route('admin.validacion_ani.index') }}" class="btn btn-secondary">Volver</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div id="pdf-viewer">
                        @if($pdfUrl)
                            <iframe src="{{ $pdfUrl }}" width="100%" height="100%" frameborder="0"></iframe>
                        @else
                            <div class="alert alert-light border">No hay PDF cargado.</div>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
@stop

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            $('.js-example-basic-single').select2();
        });
    </script>
@endsection
