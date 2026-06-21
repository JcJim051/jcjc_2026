@extends('public.coordinador_reportes.layout')
@section('title', 'Alertas por zona electoral')
@section('brand_title', 'Alertas por zona electoral')
@section('brand_subtitle', ($eleccion->nombre ?? 'N/D') . ' | ' . ($territorio->municipio ?? 'Municipio') . ' | ' . ($zonasLabel ?? 'Zona N/D'))
@section('content_body')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
    <div>
        <div><strong>Responsable:</strong> {{ $token->responsable ?: 'N/D' }}</div>
        <div><strong>Cobertura:</strong> {{ $territorio->municipio ?? 'N/D' }} - {{ $zonasLabel ?? 'Zona N/D' }}</div>
    </div>
    <div class="badge-soft {{ $token->activo ? 'badge-ok' : 'badge-empty' }}">
        {{ $token->activo ? 'Token activo' : 'Token inactivo' }}
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">Puestos</div><div class="value">{{ $kpis['puestos_total'] }}</div></div></div>
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">Mesas</div><div class="value">{{ $kpis['mesas_total'] }}</div></div></div>
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">E14</div><div class="value">{{ $kpis['mesas_e14'] }}</div></div></div>
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">Control final</div><div class="value">{{ $kpis['mesas_control'] }}</div></div></div>
</div>
<div class="row mb-3">
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">Balance</div><div class="value">{{ $kpis['alertas_balance'] }}</div></div></div>
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">Reclamaciones</div><div class="value">{{ $kpis['reclamaciones'] }}</div></div></div>
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">Reconteos</div><div class="value">{{ $kpis['reconteos'] }}</div></div></div>
    <div class="col-md-3 mb-2"><div class="metric-box"><div class="label">Jurados &lt; 6</div><div class="value">{{ $kpis['jurados_alerta'] }}</div></div></div>
</div>

<div class="card soft-card mb-3">
    <div class="card-header">Selecciona un colegio</div>
    <div class="card-body table-responsive">
        <table id="comisionPuestosTable" class="table table-bordered table-sm mb-0" style="width:100%">
            <thead>
                <tr>
                    <th>Comuna</th>
                    <th>Colegio</th>
                    <th>Mesas</th>
                    <th>E14</th>
                    <th>Control final</th>
                    <th>Balance</th>
                    <th>Reclamación</th>
                    <th>Reconteo</th>
                    <th>Jurados &lt; 6</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($puestos as $puesto)
                    <tr>
                        <td>{{ $puesto['comuna'] ?: '-' }}</td>
                        <td>{{ $puesto['puesto'] }}</td>
                        <td>{{ $puesto['mesas_total'] }}</td>
                        <td>{{ $puesto['mesas_e14'] }}</td>
                        <td>{{ $puesto['mesas_control'] }}</td>
                        <td>{{ $puesto['alertas_balance'] }}</td>
                        <td>{{ $puesto['alertas_reclamacion'] }}</td>
                        <td>{{ $puesto['alertas_reconteo'] }}</td>
                        <td>{{ $puesto['alertas_jurados'] }}</td>
                        <td>
                            <a href="{{ route('public.comision_alertas.index', ['token' => $token->token, 'puesto_id' => $puesto['puesto_id']]) }}" class="btn btn-outline-primary btn-sm">Ver mesas</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($puestoSeleccionado)
<div class="card soft-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <span>Mesas de {{ $puestoSeleccionado['puesto'] }}</span>
        <a href="{{ route('public.comision_alertas.index', $token->token) }}" class="btn btn-outline-secondary btn-sm">Cambiar colegio</a>
    </div>
    <div class="card-body table-responsive">
        <table id="comisionAlertasTable" class="table table-bordered table-sm mb-0" style="width:100%">
            <thead>
                <tr>
                    <th>Mesa</th>
                    <th>Coordinador reportó</th>
                    <th>E14</th>
                    <th>Control final</th>
                    <th>Alerta balance</th>
                    <th>Alerta reclamación</th>
                    <th>Alerta reconteo</th>
                    <th>Alerta jurados &lt; 6</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alertRows as $row)
                    <tr>
                        <td>{{ $row['mesa_num'] }}</td>
                        <td>{{ $row['coordinador_reporto'] }}</td>
                        <td><span class="badge-soft {{ $row['e14_estado'] === 'Sí' ? 'badge-ok' : 'badge-empty' }}">{{ $row['e14_estado'] }}</span></td>
                        <td><span class="badge-soft {{ $row['control_estado'] === 'Completo' ? 'badge-ok' : 'badge-pending' }}">{{ $row['control_estado'] }}</span></td>
                        <td><span class="badge-soft {{ $row['balance_alerta'] === 'Alerta' ? 'badge-pending' : ($row['balance_alerta'] === 'OK' ? 'badge-ok' : 'badge-empty') }}">{{ $row['balance_alerta'] }}</span></td>
                        <td><span class="badge-soft {{ $row['reclamacion_alerta'] === 'Alerta' ? 'badge-pending' : 'badge-ok' }}">{{ $row['reclamacion_alerta'] }}</span></td>
                        <td><span class="badge-soft {{ $row['reconteo_alerta'] === 'Alerta' ? 'badge-pending' : 'badge-ok' }}">{{ $row['reconteo_alerta'] }}</span></td>
                        <td><span class="badge-soft {{ $row['jurados_alerta'] === 'Alerta' ? 'badge-pending' : ($row['jurados_alerta'] === 'OK' ? 'badge-ok' : 'badge-empty') }}">{{ $row['jurados_alerta'] }}</span></td>
                        <td><a href="{{ route('public.comision_alertas.show', [$token->token, $row['mesa_id']]) }}" class="btn btn-outline-primary btn-sm">Ver</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script>
$(function () {
    $('#comisionPuestosTable').DataTable({
        responsive: true,
        scrollX: true,
        pageLength: 25,
        order: [[0, 'asc'], [1, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        }
    });

    if ($('#comisionAlertasTable').length) {
        $('#comisionAlertasTable').DataTable({
            responsive: true,
            scrollX: true,
            pageLength: 50,
            order: [[0, 'asc']],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
            }
        });
    }
});
</script>
@endpush
