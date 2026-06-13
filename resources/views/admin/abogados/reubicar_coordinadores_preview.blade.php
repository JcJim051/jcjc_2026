@extends('adminlte::page')

@section('title', 'Ajuste coordinadores')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h2>Ajuste transitorio de coordinadores</h2>
        <a href="{{ route('admin.abogados.index') }}" class="btn btn-secondary btn-sm">Volver</a>
    </div>
@stop

@section('content')
    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!empty($applied))
        <div class="alert alert-success">
            <strong>Ajuste ejecutado.</strong>
            Coordinaciones actualizadas: {{ $apply_summary['actualizados'] ?? 0 }}.
            Cambios reales de mesa: {{ $apply_summary['cambios_reales'] ?? 0 }}.
            Sin mesa libre: {{ $apply_summary['sin_mesa'] ?? 0 }}.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-1">{{ $eleccion->nombre }}</h4>
                    <div class="text-muted">
                        Tipo: {{ $eleccion->tipo ?: '-' }} | Estado: {{ $eleccion->estado ?: '-' }}
                    </div>
                </div>
                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                    <form method="POST" action="{{ route('admin.abogados.reubicar_coordinadores.apply') }}" onsubmit="return confirm('¿Aplicar este ajuste transitorio sobre la elección seleccionada?');">
                        @csrf
                        <input type="hidden" name="eleccion_id" value="{{ $eleccion->id }}">
                        <button type="submit" class="btn btn-primary" {{ ($summary['reubicables'] ?? 0) === 0 ? 'disabled' : '' }}>
                            Aplicar válidos
                        </button>
                    </form>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-3 col-6 mb-3">
                    <div class="small text-muted">Coordinadores a procesar</div>
                    <div class="h4 mb-0">{{ $summary['procesados'] ?? 0 }}</div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="small text-muted">Reubicables</div>
                    <div class="h4 mb-0 text-success">{{ $summary['reubicables'] ?? 0 }}</div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="small text-muted">Sin mesa libre</div>
                    <div class="h4 mb-0 text-danger">{{ $summary['sin_mesa'] ?? 0 }}</div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="small text-muted">Puestos afectados</div>
                    <div class="h4 mb-0">{{ $summary['puestos_afectados'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Detalle de coordinaciones</strong>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-bordered table-sm mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Abogado</th>
                        <th>Cédula</th>
                        <th>Puesto</th>
                        <th>Actual</th>
                        <th>Sugerida</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['coordinacion_id'] }}</td>
                            <td>{{ $row['abogado_nombre'] }}</td>
                            <td>{{ $row['abogado_cc'] ?: '-' }}</td>
                            <td>{{ $row['puesto_label'] }}</td>
                            <td>{{ $row['current_label'] }}</td>
                            <td>{{ $row['target_label'] }}</td>
                            <td>
                                @if($row['status'] === 'reubicar')
                                    <span class="badge badge-success">Listo para ajustar</span>
                                @else
                                    <span class="badge badge-danger">Sin mesa libre</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No hay coordinadores activos en esta elección.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
