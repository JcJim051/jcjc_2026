@extends('adminlte::page')

@section('title', 'Editar Abogado')

@section('content_header')
    <h2>Editar Abogado</h2>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.abogados.update', $abogado->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card card-outline card-primary">
                    <div class="card-header"><h6 class="mb-0">Datos Personales</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 form-group"><label>Nombre Completo</label><input type="text" name="nombre" class="form-control" value="{{ old('nombre', $abogado->nombre) }}" required></div>
                            <div class="col-md-4 form-group"><label>Cédula</label><input type="text" name="cc" class="form-control" value="{{ old('cc', $abogado->cc) }}" required></div>
                            <div class="col-md-4 form-group"><label>Correo</label><input type="email" name="correo" class="form-control" value="{{ old('correo', $abogado->correo) }}" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group"><label>Dirección</label><input type="text" name="direccion" class="form-control" value="{{ old('direccion', $abogado->direccion) }}" required></div>
                            <div class="col-md-6 form-group"><label>Comuna</label><input type="text" name="comuna" class="form-control" value="{{ old('comuna', $abogado->comuna) }}" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 form-group"><label>Teléfono</label><input type="text" name="telefono" class="form-control" value="{{ old('telefono', $abogado->telefono) }}" required></div>
                            <div class="col-md-5 form-group">
                                <label>Puesto de Votación</label>
                                <select name="puesto" id="puesto" class="form-control js-puesto-select" required>
                                    <option value="">Seleccione un puesto...</option>
                                    @php $puestoSeleccionado = old('puesto', $abogado->puesto); @endphp
                                    @foreach(($puestos ?? collect()) as $puesto)
                                        <option value="{{ $puesto }}" {{ $puestoSeleccionado === $puesto ? 'selected' : '' }}>
                                            {{ $puesto }}
                                        </option>
                                    @endforeach
                                    @if($puestoSeleccionado && !collect($puestos ?? [])->contains($puestoSeleccionado))
                                        <option value="{{ $puestoSeleccionado }}" selected>{{ $puestoSeleccionado }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-4 form-group"><label>Mesa</label><input type="text" name="mesa" class="form-control" value="{{ old('mesa', $abogado->mesa) }}" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group"><label>Foto</label><input type="file" name="foto" class="form-control" accept="image/*"></div>
                            <div class="col-md-4 form-group"><label>PDF Cédula</label><input type="file" name="pdf_cc" class="form-control" accept=".pdf"></div>
                            <div class="col-md-4 form-group"><label>Disponibilidad</label><input type="text" name="disponibilidad" class="form-control" value="{{ old('disponibilidad', $abogado->disponibilidad) }}"></div>
                        </div>
                        <div class="form-group"><label>Observación</label><input type="text" name="observacion" class="form-control" value="{{ old('observacion', $abogado->observacion) }}"></div>
                    </div>
                </div>

                <div class="card card-outline card-success">
                    <div class="card-header"><h6 class="mb-0">Académicos</h6></div>
                    <div class="card-body row">
                        <div class="col-md-6 form-group"><label>Nivel de estudios</label><input type="text" name="estudios" class="form-control" value="{{ old('estudios', $abogado->estudios) }}"></div>
                        <div class="col-md-6 form-group"><label>Título obtenido</label><input type="text" name="titulo" class="form-control" value="{{ old('titulo', $abogado->titulo) }}"></div>
                    </div>
                </div>

                <div class="card card-outline card-warning">
                    <div class="card-header"><h6 class="mb-0">Laboral</h6></div>
                    <div class="card-body row">
                        <div class="col-md-4 form-group"><label>Entidad</label><input type="text" name="entidad" class="form-control" value="{{ old('entidad', $abogado->entidad) }}"></div>
                        <div class="col-md-4 form-group"><label>Secretaría</label><input type="text" name="secretaria" class="form-control" value="{{ old('secretaria', $abogado->secretaria) }}"></div>
                        <div class="col-md-4 form-group"><label>Honorarios</label><input type="text" name="honorarios" class="form-control" value="{{ old('honorarios', $abogado->honorarios) }}"></div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1" {{ old('activo', $abogado->activo) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="activo">Activo</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ route('admin.abogados.index') }}" class="btn btn-secondary">Volver</a>
            </form>
        </div>
    </div>
@stop

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            $('.js-puesto-select').select2({
                width: '100%',
                placeholder: 'Seleccione un puesto...',
                allowClear: true
            });
        });
    </script>
@stop
