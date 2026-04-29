@extends('adminlte::page')

@section('title', 'Validacion ANI')

@section('content_header')
    <h1>Validacion ANI - Coordinador Rem {{ $puesto->nombre ?? $coordinacion->codpuesto }}</h1>
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
        <form action="{{ route('admin.validacion_ani.coordinador.update', $coordinacion->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card card-outline card-warning">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label for="historial_observaciones">Historial (solo lectura)</label>
                            <textarea id="historial_observaciones" class="form-control" rows="5" readonly>{{ $coordinacion->observaciones }}</textarea>
                        </div>
                        <div class="col-md-5">
                            <label for="nueva_observacion">Nueva observación</label>
                            <textarea name="nueva_observacion" id="nueva_observacion" class="form-control" rows="5" placeholder="Escribe una nueva observación...">{{ old('nueva_observacion') }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label for="estado">Estado</label>
                            <select name="estado" id="estado" class="form-control" required>
                                <option value="asignado" {{ old('estado', $coordinacion->validacion_estado ?? 'asignado') === 'asignado' ? 'selected' : '' }}>Asignado</option>
                                <option value="validado" {{ old('estado', $coordinacion->validacion_estado ?? 'asignado') === 'validado' ? 'selected' : '' }}>Validado</option>
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
                                <input type="text" class="form-control" name="nombre" id="nombre" value="{{ old('nombre', $abogado->nombre ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="cedula">Cedula</label>
                                <input type="text" class="form-control" name="cedula" id="cedula" value="{{ old('cedula', $abogado->cc ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" name="email" id="email" value="{{ old('email', $abogado->correo ?? '') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="telefono">Telefono</label>
                                <input type="text" class="form-control" name="telefono" id="telefono" value="{{ old('telefono', $abogado->telefono ?? '') }}">
                            </div>
                            <div class="row">
                                <div class="col-md-9">
                                    <label>Puesto de votación</label>
                                    <input type="text" class="form-control" value="{{ ($puesto->mun ?? '') . ' - ' . ($puesto->nombre ?? $coordinacion->codpuesto) }}" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label>Mesa</label>
                                    <input type="text" class="form-control" value="Rem" readonly>
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
