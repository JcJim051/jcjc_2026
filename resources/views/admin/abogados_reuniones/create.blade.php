@extends('adminlte::page')

@section('title', 'Admin')

@section('content_header')
    <h2>Crear reunión del equipo para asistencia por QR</h2>
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
            <form method="POST" action="{{ route('admin.abogados_reuniones.store') }}">
                @csrf
                <div class="card card-outline card-warning">
                    <div class="card-header">
                        <h6 class="mb-0">Datos de la reunión</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-3 form-group">
                                <label for="fecha">Fecha</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" value="{{ old('fecha') }}" required>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label for="hora_inicio">Hora inicio</label>
                                <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" value="{{ old('hora_inicio') }}" required>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label for="hora_fin">Hora fin</label>
                                <input type="time" class="form-control" id="hora_fin" name="hora_fin" value="{{ old('hora_fin') }}" required>
                            </div>
                            <div class="col-sm-3 form-group">
                                <label for="aforo">Aforo</label>
                                <input class="form-control" type="text" id="aforo" name="aforo" value="{{ old('aforo') }}" placeholder="Numero de personas">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 form-group">
                                <label for="lugar">Lugar</label>
                                <input class="form-control" type="text" id="lugar" name="lugar" value="{{ old('lugar') }}" placeholder="Digite la dirección o sede" required>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Crear reunión</button>
                <a href="{{ route('admin.abogados_reuniones.index') }}" class="btn btn-secondary">Volver</a>
            </form>
        </div>
    </div>
@stop
