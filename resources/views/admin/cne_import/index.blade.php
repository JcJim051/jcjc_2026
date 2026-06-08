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
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Revisa el formulario:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-primary">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0">Crear referidos masivos desde Excel</h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Carga un Excel/CSV ya estructurado. Primero se crea una previsualizacion obligatoria;
                solo al confirmar se crean los referidos validos.
            </p>
            <form action="{{ route('admin.cne_import.referidos_masivos.preview') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-sm-3">
                        <label>Eleccion</label>
                        <select name="eleccion_id" id="mass_eleccion_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            @foreach ($elecciones as $e)
                                <option value="{{ $e->id }}">{{ $e->id }} - {{ $e->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label>Token territorio</label>
                        <select name="territorio_token_id" id="mass_token_id" class="form-control" required>
                            <option value="">Seleccione una eleccion primero...</option>
                            @foreach ($tokens as $token)
                                <option value="{{ $token->id }}" data-eleccion="{{ $token->eleccion_id }}">
                                    #{{ $token->id }} - {{ $token->responsable ?: 'Sin encargado' }}
                                    {{ $token->es_consulta ? '(consulta)' : '' }}
                                    {{ $token->comuna ? '- Comuna ' . $token->comuna : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label>Archivo referidos (xlsx/csv/txt)</label>
                        <input type="file" name="archivo" class="form-control" accept=".xlsx,.csv,.txt" required>
                    </div>
                    <div class="col-sm-2" style="padding-top: 30px;">
                        <button class="btn btn-primary btn-block" type="submit">Previsualizar</button>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    Columnas obligatorias: cedula, nombre, puesto, mesa. Opcionales: municipio, correo, telefono, observacion.
                </small>
            </form>
        </div>
    </div>

    @if ($batches->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Lotes recientes de referidos masivos</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Archivo</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Validos</th>
                            <th>Errores</th>
                            <th>Importados</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            <tr>
                                <td>#{{ $batch->id }}</td>
                                <td>{{ $batch->filename ?: '-' }}</td>
                                <td><span class="badge badge-secondary">{{ $batch->status }}</span></td>
                                <td>{{ $batch->total_rows }}</td>
                                <td>{{ $batch->valid_rows }}</td>
                                <td>{{ $batch->error_rows }}</td>
                                <td>{{ $batch->imported_rows }}</td>
                                <td>{{ optional($batch->created_at)->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.cne_import.referidos_masivos.show', $batch) }}" class="btn btn-xs btn-outline-primary">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
                        <label>Archivo POSTULADOS (xlsx/csv)</label>
                        <input type="file" name="archivo" class="form-control" accept=".xlsx,.csv,.txt" required>
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
                        <label>Archivo ACREDITADOS (xlsx/csv)</label>
                        <input type="file" name="archivo" class="form-control" accept=".xlsx,.csv,.txt" required>
                    </div>
                    <div class="col-sm-2" style="padding-top: 30px;">
                        <button class="btn btn-success" type="submit">Importar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Descargar Asignados Pendientes de Postulacion</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.cne_import.asignados_pendientes') }}" method="GET">
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
                    <div class="col-sm-3" style="padding-top: 30px;">
                        <button class="btn btn-warning" type="submit">Descargar Excel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const electionSelect = document.getElementById('mass_eleccion_id');
            const tokenSelect = document.getElementById('mass_token_id');
            const tokenOptions = Array.from(tokenSelect.querySelectorAll('option')).map(option => ({
                value: option.value,
                text: option.text,
                eleccion: option.dataset.eleccion || ''
            }));

            function refreshTokens() {
                const electionId = electionSelect.value;
                tokenSelect.innerHTML = '';

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = electionId ? 'Seleccione token...' : 'Seleccione una eleccion primero...';
                tokenSelect.appendChild(placeholder);

                tokenOptions.forEach(item => {
                    if (!item.value || item.eleccion !== electionId) {
                        return;
                    }
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.text;
                    option.dataset.eleccion = item.eleccion;
                    tokenSelect.appendChild(option);
                });
            }

            electionSelect.addEventListener('change', refreshTokens);
            refreshTokens();
        });
    </script>
@stop
