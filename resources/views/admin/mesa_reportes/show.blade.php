@extends('adminlte::page')
@section('title', 'Detalle de Mesa')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <div>
        <h1 class="mb-1">Detalle operativo de mesa</h1>
        <div class="text-muted">
            {{ $mesa['municipio'] }} | {{ $mesa['comuna'] }} | {{ $mesa['puesto'] }} | Mesa {{ $mesa['mesa_num'] }}
        </div>
    </div>
    <div class="d-flex" style="gap:8px;">
        <a href="{{ route('admin.mesa_reportes.mesas', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">Volver a alertas</a>
        <a href="{{ route('admin.mesa_reportes.e14', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">E14</a>
    </div>
</div>
@stop

@section('content')
@php
    $row = $mesa['row'];
    $detalleRows = collect($e14DetalleRows ?? []);
@endphp

<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Estado operativo</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Elección</dt>
                    <dd class="col-sm-7">{{ $eleccionOperativa->nombre ?? 'N/D' }}</dd>
                    <dt class="col-sm-5">Municipio</dt>
                    <dd class="col-sm-7">{{ $mesa['municipio'] }}</dd>
                    <dt class="col-sm-5">Comuna</dt>
                    <dd class="col-sm-7">{{ $mesa['comuna'] }}</dd>
                    <dt class="col-sm-5">Puesto</dt>
                    <dd class="col-sm-7">{{ $mesa['puesto'] }}</dd>
                    <dt class="col-sm-5">Mesa</dt>
                    <dd class="col-sm-7">{{ $mesa['mesa_num'] }}</dd>
                    <dt class="col-sm-5">Coordinador reportó</dt>
                    <dd class="col-sm-7">{{ $mesa['coordinador_reporto'] }}</dd>
                    <dt class="col-sm-5">E14 cargado</dt>
                    <dd class="col-sm-7">{{ $mesa['e14_estado'] }}</dd>
                    <dt class="col-sm-5">Control final</dt>
                    <dd class="col-sm-7">{{ $mesa['control_estado'] }}</dd>
                    <dt class="col-sm-5">Reconteo</dt>
                    <dd class="col-sm-7">{{ $mesa['reconteo_resumen'] }}</dd>
                    <dt class="col-sm-5">Reclamación</dt>
                    <dd class="col-sm-7">{{ $mesa['reclamacion_resumen'] }}</dd>
                    <dt class="col-sm-5">Jurados firmaron</dt>
                    <dd class="col-sm-7">{{ $mesa['jurados_firmaron'] }}</dd>
                    <dt class="col-sm-5">Reporte E14</dt>
                    <dd class="col-sm-7">{{ $row->e14_reportado_at ? \Carbon\Carbon::parse($row->e14_reportado_at)->format('Y-m-d H:i:s') : 'N/D' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Alertas de la mesa</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Balance</div>
                            <div class="h5 mb-1">{{ $mesa['balance_alerta'] }}</div>
                            <div>{{ $mesa['balance_resumen'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Reclamación</div>
                            <div class="h5 mb-1">{{ $mesa['reclamacion_alerta'] }}</div>
                            <div>{{ $mesa['reclamacion_resumen'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Reconteo</div>
                            <div class="h5 mb-1">{{ $mesa['reconteo_alerta'] }}</div>
                            <div>{{ $mesa['reconteo_resumen'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Jurados firmaron</div>
                            <div class="h5 mb-1">{{ $mesa['jurados_alerta'] }}</div>
                            <div>{{ $mesa['jurados_firmaron'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Coordinadores del puesto</strong></div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Mesa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($coordinadores as $coord)
                            <tr>
                                <td>
                                    {{ $coord->nombre }}
                                    @if((int) $coord->abogado_id === (int) ($mesa['coordinador_id'] ?? 0))
                                        <span class="badge badge-info">Reportó</span>
                                    @endif
                                </td>
                                <td>{{ $coord->telefono ?: 'N/D' }}</td>
                                <td>{{ $coord->email ?: 'N/D' }}</td>
                                <td>{{ $coord->mesa_num ?? 'Rem / Sin mesa' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No hay coordinadores activos en este puesto.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Datos E14</strong></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Censo mesa:</strong> {{ $row->censo_mesa ?? 0 }}</div>
                    <div class="col-md-4"><strong>Votos en urna:</strong> {{ $row->votos_en_urna ?? 0 }}</div>
                    <div class="col-md-4"><strong>Incinerados:</strong> {{ $row->votos_incinerados ?? 0 }}</div>
                    <div class="col-md-4"><strong>Blancos:</strong> {{ $row->votos_blanco ?? 0 }}</div>
                    <div class="col-md-4"><strong>Nulos:</strong> {{ $row->votos_nulos ?? 0 }}</div>
                    <div class="col-md-4"><strong>No marcados:</strong> {{ $row->votos_no_marcados ?? 0 }}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Candidato</th>
                                <th>Votos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($detalleRows as $item)
                                <tr>
                                    <td>{{ $item['codigo'] ?? '-' }}</td>
                                    <td>{{ $item['nombre'] ?? '-' }}</td>
                                    <td>{{ $item['votos'] ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">No hay candidatos configurados para esta elección.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total votos calculado</th>
                                <th>{{ $mesa['balance_total_votos'] ?? 0 }}</th>
                            </tr>
                            <tr>
                                <th colspan="2">Resultado balance</th>
                                <th>{{ $mesa['balance_resumen'] }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Evidencias</strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Foto E14:</strong><br>
                    @if(!empty($row->e14_foto_path))
                        <a href="{{ asset('storage/' . $row->e14_foto_path) }}" target="_blank">Ver foto E14</a>
                    @else
                        <span class="text-muted">No cargada</span>
                    @endif
                </div>
                <div class="mb-3">
                    <strong>Foto reclamación:</strong><br>
                    @if(!empty($row->reclamacion_foto_path))
                        <a href="{{ asset('storage/' . $row->reclamacion_foto_path) }}" target="_blank">Ver foto reclamación</a>
                    @else
                        <span class="text-muted">No cargada</span>
                    @endif
                </div>
                <div class="mb-3">
                    <strong>Comentario de reclamación:</strong><br>
                    <span>{{ $row->reclamacion_comentario ?: 'N/D' }}</span>
                </div>
                <div class="mb-3">
                    <strong>Origen:</strong><br>
                    <span>{{ $row->reclamacion_origen ?: 'N/D' }}</span>
                </div>
                <div>
                    <strong>Candidato relacionado:</strong><br>
                    <span>{{ $reclamacionCandidato ?: 'N/D' }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Vista rápida de imágenes</strong></div>
            <div class="card-body">
                @if(!empty($row->e14_foto_path))
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Foto E14</div>
                        <img src="{{ asset('storage/' . $row->e14_foto_path) }}" alt="Foto E14" class="img-fluid border rounded">
                    </div>
                @endif
                @if(!empty($row->reclamacion_foto_path))
                    <div>
                        <div class="text-muted small mb-1">Foto reclamación</div>
                        <img src="{{ asset('storage/' . $row->reclamacion_foto_path) }}" alt="Foto reclamación" class="img-fluid border rounded">
                    </div>
                @endif
                @if(empty($row->e14_foto_path) && empty($row->reclamacion_foto_path))
                    <div class="text-muted">No hay imágenes cargadas para esta mesa.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Observaciones de comisión</strong></div>
    <div class="card-body">
        @forelse($comentarios as $comentario)
            <div class="border rounded p-3 mb-2">
                <div class="d-flex justify-content-between flex-wrap" style="gap:6px;">
                    <strong>{{ $comentario->autor_nombre }}</strong>
                    <span class="text-muted small">{{ optional($comentario->created_at)->format('Y-m-d H:i:s') }}</span>
                </div>
                <div class="mt-2">{{ $comentario->comentario }}</div>
            </div>
        @empty
            <div class="text-muted">No hay observaciones de comisión para esta mesa.</div>
        @endforelse
    </div>
</div>
@stop
