@extends('adminlte::page')

@section('title', 'Candidatos')

@section('content_header')
    <h1 style="text-align:center">Candidatos</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <form method="GET" action="{{ route('admin.candidatos.index') }}" class="form-inline">
                <label class="mr-2">Elección</label>
                <select name="eleccion_id" class="form-control mr-2">
                    <option value="">Todas</option>
                    @foreach ($elecciones as $e)
                        <option value="{{ $e->id }}" {{ (string) $eleccionId === (string) $e->id ? 'selected' : '' }}>
                            {{ $e->nombre }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>

            <a href="{{ route('admin.candidatos.create') }}" class="btn btn-success">Crear candidato</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Elección</th>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Partido</th>
                        <th>Campo E14</th>
                        <th>Activo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($candidatos as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td>{{ $c->eleccion_id }}</td>
                            <td>{{ $c->codigo }}</td>
                            <td>{{ $c->nombre }}</td>
                            <td>{{ $c->partido ?? '-' }}</td>
                            <td>{{ $c->campo_e14 ? ('gob' . $c->campo_e14) : '-' }}</td>
                            <td>{{ $c->activo ? 'Sí' : 'No' }}</td>
                            <td>
                                <a class="btn btn-sm btn-primary" href="{{ route('admin.candidatos.edit', $c) }}">Editar</a>
                                <form action="{{ route('admin.candidatos.destroy', $c) }}" method="POST" style="display:inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">Sin candidatos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
