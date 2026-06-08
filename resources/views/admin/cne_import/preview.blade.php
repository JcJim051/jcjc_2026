@extends('adminlte::page')

@section('title', 'Previsualizacion Importacion Referidos')

@section('content_header')
    <h1 class="text-center">Previsualizacion de Referidos Masivos</h1>
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
        <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0">Lote #{{ $batch->id }}</h3>
            <span class="badge badge-{{ $batch->status === 'aplicado' ? 'success' : ($batch->status === 'cancelado' ? 'danger' : 'info') }}">
                {{ strtoupper($batch->status) }}
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Eleccion</strong><br>
                    {{ optional($eleccion)->nombre ?: ('#' . $batch->eleccion_id) }}
                </div>
                <div class="col-md-3">
                    <strong>Token</strong><br>
                    #{{ $batch->territorio_token_id }} - {{ optional($token)->responsable ?: 'Sin encargado' }}
                </div>
                <div class="col-md-3">
                    <strong>Archivo</strong><br>
                    {{ $batch->filename ?: '-' }}
                </div>
                <div class="col-md-3">
                    <strong>Creado</strong><br>
                    {{ optional($batch->created_at)->format('Y-m-d H:i') }}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-2 col-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $batch->total_rows }}</h3>
                    <p>Total filas</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $batch->valid_rows }}</h3>
                    <p>Validas</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $batch->error_rows }}</h3>
                    <p>Errores</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $counters->get('importados', 0) }}</h3>
                    <p>Importados</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body py-3">
                    <strong>Detalle de errores</strong>
                    <div class="mt-2">
                        @foreach (['duplicado', 'puesto_no_encontrado', 'puesto_ambiguo', 'mesa_invalida', 'mesa_ocupada', 'mesa_bloqueada', 'incompleto', 'mesa_ocupada_lote'] as $code)
                            @if ($counters->get($code, 0) > 0)
                                <span class="badge badge-warning mr-1">{{ $code }}: {{ $counters->get($code) }}</span>
                            @endif
                        @endforeach
                        @if ($batch->error_rows == 0)
                            <span class="text-muted">Sin errores.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body d-flex flex-wrap align-items-center" style="gap: 8px;">
            <a href="{{ route('admin.cne_import.index') }}" class="btn btn-secondary">Volver</a>

            @if ($batch->status === 'preview' && $batch->valid_rows > 0)
                <form action="{{ route('admin.cne_import.referidos_masivos.apply', $batch) }}" method="POST" onsubmit="return confirm('¿Aplicar solo las filas validas de este lote?');">
                    @csrf
                    <button type="submit" class="btn btn-success">Aplicar validos</button>
                </form>
            @endif

            @if ($batch->error_rows > 0)
                <a href="{{ route('admin.cne_import.referidos_masivos.errors', $batch) }}" class="btn btn-warning">Descargar errores</a>
            @endif

            @if ($batch->status === 'preview')
                <form action="{{ route('admin.cne_import.referidos_masivos.cancel', $batch) }}" method="POST" onsubmit="return confirm('¿Cancelar este lote? No se crearan referidos.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Cancelar lote</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filas del lote</h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fila</th>
                        <th>Cedula</th>
                        <th>Nombre</th>
                        <th>Telefono</th>
                        <th>Municipio</th>
                        <th>Puesto</th>
                        <th>Mesa</th>
                        <th>Estado</th>
                        <th>Observacion</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batch->rows as $row)
                        <tr>
                            <td>{{ $row->row_number }}</td>
                            <td>{{ $row->cedula ?: '-' }}</td>
                            <td>{{ $row->nombre ?: '-' }}</td>
                            <td>{{ $row->telefono ?: '-' }}</td>
                            <td>{{ $row->municipio_texto ?: '-' }}</td>
                            <td>{{ $row->puesto_texto ?: '-' }}</td>
                            <td>{{ $row->mesa_num ?: '-' }}</td>
                            <td>
                                @if ($row->status === 'valid')
                                    <span class="badge badge-success">VALIDA</span>
                                @elseif ($row->status === 'importado')
                                    <span class="badge badge-info">IMPORTADA</span>
                                @else
                                    <span class="badge badge-danger">ERROR</span>
                                @endif
                            </td>
                            <td>{{ $row->observacion ?: '-' }}</td>
                            <td>
                                @if ($row->error_code)
                                    <strong>{{ $row->error_code }}:</strong>
                                @endif
                                {{ $row->error_message ?: '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop
