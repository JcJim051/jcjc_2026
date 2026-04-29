@extends('adminlte::page')

@section('title', 'Abogados')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h2>Caracterización de Abogados</h2>
        <a href="{{ route('admin.abogados.create') }}" class="btn btn-primary btn-sm">Crear nuevo</a>
    </div>
@stop

@section('content')
    @if (session('info'))
        <div class="alert alert-success"><strong>{{ session('info') }}</strong></div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm" id="abogadosTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Activo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($abogados as $abogado)
                        <tr>
                            <td>{{ $abogado->id }}</td>
                            <td>{{ $abogado->nombre }}</td>
                            <td>{{ $abogado->telefono ?? '-' }}</td>
                            <td>{{ $abogado->correo ?? '-' }}</td>
                            <td>{!! $abogado->activo ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-secondary">No</span>' !!}</td>
                            <td>
                                <a href="{{ route('admin.abogados.show', $abogado->id) }}" class="btn btn-success btn-sm">Ver</a>
                                <a href="{{ route('admin.abogados.edit', $abogado->id) }}" class="btn btn-primary btn-sm">Editar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop

