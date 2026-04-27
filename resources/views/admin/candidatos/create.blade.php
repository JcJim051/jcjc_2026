@extends('adminlte::page')

@section('title', 'Crear Candidato')

@section('content_header')
    <h1>Crear candidato</h1>
@stop

@section('content')
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
            <form method="POST" action="{{ route('admin.candidatos.store') }}">
                @csrf
                <div class="form-group">
                    <label>Elección</label>
                    <select name="eleccion_id" class="form-control" required>
                        @foreach ($elecciones as $e)
                            <option value="{{ $e->id }}">{{ $e->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Código</label>
                    <input type="number" name="codigo" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Partido</label>
                    <input type="text" name="partido" class="form-control">
                </div>

                <div class="form-group">
                    <label>Campo en formulario E14</label>
                    <select name="campo_e14" class="form-control">
                        <option value="">No incluir en Reportar E14</option>
                        @for ($i = 1; $i <= 11; $i++)
                            <option value="{{ $i }}" {{ (string) old('campo_e14') === (string) $i ? 'selected' : '' }}>
                                gob{{ $i }}
                            </option>
                        @endfor
                    </select>
                    <small class="text-muted">Cada elección puede usar cada campo `gob1..gob11` una sola vez.</small>
                </div>

                <div class="form-group">
                    <label>Activo</label>
                    <select name="activo" class="form-control" required>
                        <option value="1" selected>Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>

                <button class="btn btn-success" type="submit">Crear</button>
                <a href="{{ route('admin.candidatos.index') }}" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
@stop
