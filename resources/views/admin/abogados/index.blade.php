@extends('adminlte::page')

@section('title', 'Abogados')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h2>Caracterización de Abogados</h2>
        <div class="d-flex align-items-center" style="gap: 8px;">
            <a href="{{ route('admin.abogados.exportar') }}" class="btn btn-success btn-sm">
                <i class="fas fa-file-excel mr-1"></i> Descargar listado
            </a>
            <a href="{{ route('admin.abogados.create') }}" class="btn btn-primary btn-sm">Crear nuevo</a>
        </div>
    </div>
@stop

@section('content')
    @if (session('info'))
        <div class="alert alert-success"><strong>{{ session('info') }}</strong></div>
    @endif
    @if (session('coordinadores_import_errors'))
        <div class="alert alert-warning">
            <strong>Filas para revisar:</strong>
            <ul class="mb-0 mt-2">
                @foreach(session('coordinadores_import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm" id="abogadosTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Cédula</th>
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
                            <td>{{ $abogado->cc ?? '-' }}</td>
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

    @can('Superuser')
        <div class="card collapsed-card">
            <div class="card-header bg-success">
                <h3 class="card-title"><strong>Recuperar fotos y PDF de cédulas</strong></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" style="display: none;">
                <form method="POST" action="{{ route('admin.abogados.adjuntos.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row align-items-end">
                        <div class="col-md-7 form-group">
                            <label>Excel original de respuestas</label>
                            <input type="file" name="archivo_adjuntos" class="form-control-file" accept=".xlsx,.xls,.csv,.txt" required>
                            <small class="text-muted">Debe contener Cédula y las columnas con enlaces de foto/PDF.</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="reemplazar_existentes" name="reemplazar_existentes" value="1">
                                <label class="custom-control-label" for="reemplazar_existentes">Reemplazar adjuntos existentes</label>
                            </div>
                        </div>
                        <div class="col-md-2 form-group">
                            <button class="btn btn-success btn-block" type="submit">Previsualizar</button>
                        </div>
                    </div>
                    <div class="alert alert-light border mb-0">
                        Solo modifica los campos de foto y PDF. Los archivos incorrectos o páginas HTML de Drive serán rechazados.
                    </div>
                </form>
            </div>
        </div>

        <div class="card collapsed-card">
            <div class="card-header bg-warning">
                <h3 class="card-title"><strong>Actualización masiva de información personal</strong></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" style="display: none;">
                <form method="POST" action="{{ route('admin.abogados.actualizacion_personal.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row align-items-end">
                        <div class="col-md-8 form-group">
                            <label>Excel / CSV de información personal</label>
                            <input type="file" name="archivo" class="form-control-file" accept=".xlsx,.xls,.csv,.txt" required>
                            <small class="text-muted">
                                Cruza por cédula y revisa correo, teléfono, dirección, observación, puesto donde vota y mesa.
                            </small>
                        </div>
                        <div class="col-md-4 form-group">
                            <button type="submit" class="btn btn-warning btn-block">
                                <i class="fas fa-search mr-1"></i> Previsualizar cambios
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-light border mb-0">
                        Este proceso no modifica coordinaciones, elecciones, puestos coordinados ni cupos remanentes.
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card collapsed-card">
        <div class="card-header">
            <h3 class="card-title"><strong>Importar desde Google Sheets</strong></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body" style="display: none;">
            <form method="POST" action="{{ route('admin.abogados.import_sheet') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>URL del Spreadsheet</label>
                        <input type="url" name="spreadsheet_url" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>GID (opcional)</label>
                        <input type="text" name="gid" class="form-control" placeholder="631350820">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>CSV (alterno)</label>
                        <input type="file" name="csv_file" class="form-control-file" accept=".csv,.txt">
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Importar</button>
                    </div>
                </div>
                <small class="text-muted">Usa URL o CSV. Si el servidor no sale a Google, sube CSV exportado.</small>
            </form>
        </div>
    </div>

    <div class="card collapsed-card">
        <div class="card-header bg-info">
            <h3 class="card-title"><strong>Importar coordinadores por elección</strong></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body" style="display: none;">
            <form method="POST" action="{{ route('admin.abogados.import_coordinadores') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Elección activa</label>
                        <select name="eleccion_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            @foreach(($elecciones ?? collect()) as $e)
                                <option value="{{ $e->id }}" {{ (string) old('eleccion_id', $eleccionActiva ?? '') === (string) $e->id ? 'selected' : '' }}>
                                    #{{ $e->id }} - {{ $e->nombre }} {{ $e->tipo ? '(' . $e->tipo . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <div class="d-flex justify-content-between align-items-center"><label class="mb-0">Excel / CSV</label><a href="{{ route('admin.abogados.import_coordinadores.template') }}" class="btn btn-xs btn-outline-info">Descargar plantilla</a></div>
                        <input type="file" name="archivo" class="form-control-file" accept=".xlsx,.xls,.csv,.txt">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>URL Google Sheet (opcional)</label>
                        <input type="url" name="spreadsheet_url" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>GID</label>
                        <input type="text" name="gid" class="form-control" placeholder="0">
                    </div>
                    <div class="col-md-1 form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-info w-100">Cargar</button>
                    </div>
                </div>
                <small class="text-muted">
                    Columnas esperadas: NOMBRE, CORREO, CEDULA, DIR, PUESTO, OBSERVACION. El sistema busca el abogado por cédula, ubica el puesto en la DIVIPOL de la elección y valida cupos remanentes.
                </small>
            </form>
        </div>
    </div>

    <div class="card collapsed-card">
        <div class="card-header bg-secondary">
            <h3 class="card-title"><strong>Ajuste transitorio de coordinadores</strong></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="card-body" style="display: none;">
            <form method="POST" action="{{ route('admin.abogados.reubicar_coordinadores.preview') }}">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-8 form-group">
                        <label>Elección a ajustar</label>
                        <select name="eleccion_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            @foreach(($eleccionesReubicacion ?? collect()) as $e)
                                <option value="{{ $e->id }}" {{ (string) old('eleccion_id', $eleccionActiva ?? '') === (string) $e->id ? 'selected' : '' }}>
                                    #{{ $e->id }} - {{ $e->nombre }} {{ $e->tipo ? '(' . $e->tipo . ')' : '' }} [{{ $e->estado }}]
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <button type="submit" class="btn btn-secondary btn-block">
                            <i class="fas fa-random mr-1"></i> Previsualizar ajuste
                        </button>
                    </div>
                </div>
                <div class="alert alert-light border mb-0">
                    Reubica coordinadores activos desde <strong>Rem</strong> hacia las últimas mesas libres de cada puesto, sin mover referidos existentes.
                </div>
            </form>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#abogadosTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'asc']],
                language: {
                    search: 'Buscar:'
                }
            });
        });
    </script>
@endsection
