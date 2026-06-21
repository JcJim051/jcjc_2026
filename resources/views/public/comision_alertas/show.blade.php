@extends('public.coordinador_reportes.layout')
@section('title', 'Detalle de mesa')
@section('brand_title', 'Detalle Operativo de Mesa')
@section('brand_subtitle', ($eleccion->nombre ?? 'N/D') . ' | ' . ($mesa['municipio'] ?? 'N/D') . ' | ' . ($zonasLabel ?? ('Zona ' . ($mesa['zz'] ?? 'N/D'))))
@section('content_body')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
    <div>
        <strong>{{ $mesa['puesto'] }}</strong><br>
        Mesa {{ $mesa['mesa_num'] }} · {{ $mesa['municipio'] }} · {{ $mesa['comuna'] }}
    </div>
    <a href="{{ route('public.comision_alertas.index', $token->token) }}" class="btn btn-outline-primary">Volver a comisión</a>
</div>

@php
    $row = $mesa['row'];
    $detalle = collect($e14DetalleRows ?? [])->all();
@endphp

<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Estado operativo</div>
            <div class="card-body">
                <p class="mb-2"><strong>Coordinador reportó:</strong> {{ $mesa['coordinador_reporto'] }}</p>
                <p class="mb-2"><strong>E14:</strong> {{ $mesa['e14_estado'] }}</p>
                <p class="mb-2"><strong>Control final:</strong> {{ $mesa['control_estado'] }}</p>
                <p class="mb-2"><strong>Reconteo:</strong> {{ $mesa['reconteo_resumen'] }}</p>
                <p class="mb-2"><strong>Reclamación:</strong> {{ $mesa['reclamacion_resumen'] }}</p>
                <p class="mb-0"><strong>Jurados firmaron:</strong> {{ $mesa['jurados_firmaron'] }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Alertas</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2"><div class="metric-box"><div class="label">Balance</div><div class="value" style="font-size:1.1rem;">{{ $mesa['balance_alerta'] }}</div><div>{{ $mesa['balance_resumen'] }}</div></div></div>
                    <div class="col-md-6 mb-2"><div class="metric-box"><div class="label">Reclamación</div><div class="value" style="font-size:1.1rem;">{{ $mesa['reclamacion_alerta'] }}</div><div>{{ $mesa['reclamacion_resumen'] }}</div></div></div>
                    <div class="col-md-6 mb-2"><div class="metric-box"><div class="label">Reconteo</div><div class="value" style="font-size:1.1rem;">{{ $mesa['reconteo_alerta'] }}</div><div>{{ $mesa['reconteo_resumen'] }}</div></div></div>
                    <div class="col-md-6 mb-2"><div class="metric-box"><div class="label">Jurados</div><div class="value" style="font-size:1.1rem;">{{ $mesa['jurados_alerta'] }}</div><div>{{ $mesa['jurados_firmaron'] }}</div></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Coordinadores del puesto</div>
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
                                <td>{{ $coord->nombre }} @if((int) $coord->abogado_id === (int) ($mesa['coordinador_id'] ?? 0))<span class="badge-soft badge-ok">Reportó</span>@endif</td>
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
        <div class="card soft-card h-100">
            <div class="card-header">Datos E14</div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Censo:</strong> {{ $row->censo_mesa ?? 'N/D' }}</div>
                    <div class="col-md-4"><strong>Urna:</strong> {{ $row->votos_en_urna ?? 'N/D' }}</div>
                    <div class="col-md-4"><strong>Incinerados:</strong> {{ $row->votos_incinerados ?? 'N/D' }}</div>
                    <div class="col-md-4"><strong>Blancos:</strong> {{ $row->votos_blanco ?? 'N/D' }}</div>
                    <div class="col-md-4"><strong>Nulos:</strong> {{ $row->votos_nulos ?? 'N/D' }}</div>
                    <div class="col-md-4"><strong>No marcados:</strong> {{ $row->votos_no_marcados ?? 'N/D' }}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Candidato</th>
                                <th>Votos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($detalle as $item)
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
                                <th>{{ $mesa['balance_total_votos'] ?? 'N/D' }}</th>
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
        <div class="card soft-card h-100">
            <div class="card-header">Evidencias</div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Foto E14:</strong><br>
                    @if(!empty($row->e14_foto_path))
                        <a href="{{ asset('storage/' . $row->e14_foto_path) }}" target="_blank" rel="noopener noreferrer">Ver foto E14</a>
                    @else
                        <span class="text-muted">No cargada</span>
                    @endif
                </div>
                <div class="mb-3">
                    <strong>Foto reclamación:</strong><br>
                    @if(!empty($row->reclamacion_foto_path))
                        <a href="{{ asset('storage/' . $row->reclamacion_foto_path) }}" target="_blank" rel="noopener noreferrer">Ver foto reclamación</a>
                    @else
                        <span class="text-muted">No cargada</span>
                    @endif
                </div>
                <div class="mb-2"><strong>Origen:</strong> {{ $row->reclamacion_origen ?: 'N/D' }}</div>
                <div class="mb-2"><strong>Candidato relacionado:</strong> {{ $reclamacionCandidato ?: 'N/D' }}</div>
                <div><strong>Comentario:</strong> {{ $row->reclamacion_comentario ?: 'N/D' }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Vista rápida</div>
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

<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Historial de observaciones</div>
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
                    <div class="text-muted">No hay observaciones registradas para esta mesa.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Nueva observación</div>
            <div class="card-body">
                <form method="POST" action="{{ route('public.comision_alertas.comentarios.store', [$token->token, $mesa['mesa_id']]) }}">
                    @csrf
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="autor_nombre" class="form-control" value="{{ old('autor_nombre') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Observación</label>
                        <textarea name="comentario" class="form-control" required>{{ old('comentario') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Guardar observación</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
