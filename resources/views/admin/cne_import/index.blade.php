@extends('adminlte::page')

@section('title', 'Importacion CNE')

@section('content_header')
    <h1 style="text-align: center">Importacion CNE</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">
            <strong>{{ session('error') }}</strong>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Actualizar a Postulados</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.cne_import.postulados') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-sm-4">
                        <label>Eleccion</label>
                        <select name="eleccion_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            @foreach ($elecciones as $e)
                                <option value="{{ $e->id }}">{{ $e->id }} - {{ $e->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label>Archivo POSTULADOS (xlsx)</label>
                        <input type="file" name="archivo" class="form-control" accept=".xlsx" required>
                    </div>
                    <div class="col-sm-2" style="padding-top: 30px;">
                        <button class="btn btn-primary" type="submit">Importar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Actualizar a Acreditados</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.cne_import.acreditados') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-sm-4">
                        <label>Eleccion</label>
                        <select name="eleccion_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            @foreach ($elecciones as $e)
                                <option value="{{ $e->id }}">{{ $e->id }} - {{ $e->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label>Archivo ACREDITADOS (xlsx)</label>
                        <input type="file" name="archivo" class="form-control" accept=".xlsx" required>
                    </div>
                    <div class="col-sm-2" style="padding-top: 30px;">
                        <button class="btn btn-success" type="submit">Importar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop
