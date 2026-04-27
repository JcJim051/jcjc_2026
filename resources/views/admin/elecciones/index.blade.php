@extends('adminlte::page')

@section('title', 'Elecciones')

@section('content_header')
    <h1 style="text-align: center">Elecciones y Carga DIVIPOL</h1>
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
            <h3 class="card-title">Crear Eleccion</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.elecciones.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="tipo">Tipo</label>
                            <input type="text" name="tipo" id="tipo" class="form-control" placeholder="congreso, alcaldia, etc" required>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="fecha">Fecha</label>
                            <input type="date" name="fecha" id="fecha" class="form-control">
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select name="estado" id="estado" class="form-control" required>
                                <option value="activa">activa</option>
                                <option value="cerrada">cerrada</option>
                                <option value="planificada">planificada</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="alcance_tipo">Alcance</label>
                            <select name="alcance_tipo" id="alcance_tipo" class="form-control">
                                <option value="">Sin restricción</option>
                                <option value="nacional">Nacional</option>
                                <option value="departamental">Departamental</option>
                                <option value="municipal">Municipal</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label for="alcance_dd">Código Departamento (DD)</label>
                            <input type="text" name="alcance_dd" id="alcance_dd" class="form-control" placeholder="Ej: 50">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="alcance_mm">Municipios (MM)</label>
                            <input type="text" name="alcance_mm" id="alcance_mm" class="form-control" placeholder="Ej: 001,002,003 (opcional)">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="divipol">DIVIPOL U101 (xlsx)</label>
                            <input type="file" name="divipol" id="divipol" class="form-control" accept=".xlsx">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Crear eleccion</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Elecciones existentes</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Alcance</th>
                        <th>Importar DIVIPOL</th>
                        <th>Editar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($elecciones as $e)
                        <tr>
                            <td>{{ $e->id }}</td>
                            <td>{{ $e->nombre }}</td>
                            <td>{{ $e->tipo }}</td>
                            <td>{{ $e->fecha }}</td>
                            <td>{{ $e->estado }}</td>
                            <td>
                                {{ $e->alcance_tipo ?? 'sin' }}
                                @if($e->alcance_dd)
                                    | DD: {{ $e->alcance_dd }}
                                @endif
                                @if($e->alcance_mm)
                                    | MM: {{ $e->alcance_mm }}
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.elecciones.import', $e) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="d-flex" style="gap: 8px;">
                                        <input type="file" name="divipol" class="form-control" accept=".xlsx" required>
                                        <button type="submit" class="btn btn-sm btn-success">Importar</button>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <form action="{{ route('admin.elecciones.update', $e) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="mb-2">
                                        <input type="text" name="nombre" class="form-control form-control-sm" value="{{ $e->nombre }}" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="tipo" class="form-control form-control-sm" value="{{ $e->tipo }}" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="date" name="fecha" class="form-control form-control-sm" value="{{ $e->fecha }}">
                                    </div>
                                    <div class="mb-2">
                                        <select name="estado" class="form-control form-control-sm" required>
                                            <option value="activa" {{ $e->estado === 'activa' ? 'selected' : '' }}>activa</option>
                                            <option value="cerrada" {{ $e->estado === 'cerrada' ? 'selected' : '' }}>cerrada</option>
                                            <option value="planificada" {{ $e->estado === 'planificada' ? 'selected' : '' }}>planificada</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <select name="alcance_tipo" class="form-control form-control-sm">
                                            <option value="" {{ !$e->alcance_tipo ? 'selected' : '' }}>Sin restricción</option>
                                            <option value="nacional" {{ $e->alcance_tipo === 'nacional' ? 'selected' : '' }}>Nacional</option>
                                            <option value="departamental" {{ $e->alcance_tipo === 'departamental' ? 'selected' : '' }}>Departamental</option>
                                            <option value="municipal" {{ $e->alcance_tipo === 'municipal' ? 'selected' : '' }}>Municipal</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="alcance_dd" class="form-control form-control-sm" value="{{ $e->alcance_dd }}" placeholder="DD">
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="alcance_mm" class="form-control form-control-sm" value="{{ $e->alcance_mm }}" placeholder="MM list">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop
