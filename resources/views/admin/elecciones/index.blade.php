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
            <h3 class="card-title">Crear Elección</h3>
            <div class="card-tools">
                <a href="{{ route('admin.elecciones.divipol_template') }}" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel mr-1"></i> Descargar plantilla DIVIPOL
                </a>
            </div>
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
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label for="meta_testigos_pct">Meta % testigos</label>
                            <input type="number" name="meta_testigos_pct" id="meta_testigos_pct" class="form-control" min="0" max="100" placeholder="100">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-3"><div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input" id="crear_habilitar_afluencia" name="habilitar_afluencia" value="1" checked><label class="custom-control-label" for="crear_habilitar_afluencia">Afluencia</label></div></div>
                    <div class="col-sm-3"><div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input" id="crear_habilitar_datos_e14" name="habilitar_datos_e14" value="1" checked><label class="custom-control-label" for="crear_habilitar_datos_e14">Datos E14</label></div></div>
                    <div class="col-sm-3"><div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input" id="crear_habilitar_informacion_final" name="habilitar_informacion_final" value="1" checked><label class="custom-control-label" for="crear_habilitar_informacion_final">Información final</label></div></div>
                    <div class="col-sm-3"><div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input" id="crear_habilitar_foto_e14" name="habilitar_foto_e14" value="1" checked><label class="custom-control-label" for="crear_habilitar_foto_e14">Foto</label></div></div>
                </div>

                <div class="row">
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
                            <label for="divipol">DIVIPOL de esta elección (xlsx)</label>
                            <input type="file" name="divipol" id="divipol" class="form-control" accept=".xlsx">
                            <small class="text-muted">
                                Debe incluir comuna, dirección y cantidad de mesas vigentes para esta elección.
                            </small>
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
                        <th>Meta %</th>
                        <th>Flujo</th>
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
                            <td>{{ $e->meta_testigos_pct ?? 100 }}%</td>
                            <td>
                                <small>
                                    A: {{ $e->habilitar_afluencia ? 'On' : 'Off' }} |
                                    E14: {{ $e->habilitar_datos_e14 ? 'On' : 'Off' }} |
                                    Final: {{ $e->habilitar_informacion_final ? 'On' : 'Off' }} |
                                    Foto: {{ $e->habilitar_foto_e14 ? 'On' : 'Off' }}
                                </small>
                            </td>
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
                                        <input type="number" name="meta_testigos_pct" class="form-control form-control-sm" min="0" max="100" value="{{ $e->meta_testigos_pct }}" placeholder="100">
                                    </div>
                                    <div class="mb-2 border rounded p-2">
                                        <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="afluencia_{{ $e->id }}" name="habilitar_afluencia" value="1" {{ $e->habilitar_afluencia ? 'checked' : '' }}><label class="custom-control-label" for="afluencia_{{ $e->id }}">Afluencia</label></div>
                                        <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="datos_{{ $e->id }}" name="habilitar_datos_e14" value="1" {{ $e->habilitar_datos_e14 ? 'checked' : '' }}><label class="custom-control-label" for="datos_{{ $e->id }}">Datos E14</label></div>
                                        <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="final_{{ $e->id }}" name="habilitar_informacion_final" value="1" {{ $e->habilitar_informacion_final ? 'checked' : '' }}><label class="custom-control-label" for="final_{{ $e->id }}">Información final</label></div>
                                        <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="foto_{{ $e->id }}" name="habilitar_foto_e14" value="1" {{ $e->habilitar_foto_e14 ? 'checked' : '' }}><label class="custom-control-label" for="foto_{{ $e->id }}">Foto</label></div>
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
