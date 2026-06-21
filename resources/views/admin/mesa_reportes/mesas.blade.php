@extends('adminlte::page')
@section('title', 'Alertas por Mesa')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <div>
        <h1 class="mb-1">Alertas por mesa</h1>
        @if($eleccionOperativa)
            <div class="text-muted">Elección operativa: <strong>{{ $eleccionOperativa->nombre }}</strong></div>
        @endif
    </div>
    <div class="d-flex" style="gap:8px;">
        <a href="{{ route('admin.mesa_reportes.dashboard', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">Dashboard</a>
        <a href="{{ route('admin.mesa_reportes.afluencia', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">Afluencia</a>
        <a href="{{ route('admin.mesa_reportes.e14', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">E14</a>
        <a href="{{ route('admin.mesa_reportes.mesas', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">Alertas</a>
    </div>
</div>
@stop

@section('content')
@include('admin.mesa_reportes.partials.filters')
@can('dashboard-operativo-gestionar')
@include('admin.mesa_reportes.partials.step_toggles')
@endcan

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <h3 class="card-title mb-0">Detalle de alertas por mesa</h3>
        @can('dashboard-operativo-gestionar')
        <a href="{{ route('admin.mesa_reportes.mesas.export', ['eleccion_id' => $eleccionId, 'municipio' => $filtroMunicipio, 'puesto_id' => $filtroPuestoId, 'coordinador_id' => $filtroCoordinadorId]) }}" class="btn btn-success btn-sm">
            Descargar Excel
        </a>
        @endcan
    </div>
    <div class="card-body table-responsive">
        <table id="alertasMesasTable" class="table table-bordered table-sm display nowrap mb-0" style="width:100%">
            <thead>
                <tr>
                    <th>Municipio</th>
                    <th>Comuna</th>
                    <th>Puesto</th>
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
                        <td>{{ $row['municipio'] }}</td>
                        <td>{{ $row['comuna'] }}</td>
                        <td>{{ $row['puesto'] }}</td>
                        <td>{{ $row['mesa_num'] }}</td>
                        <td>{{ $row['coordinador_reporto'] }}</td>
                        <td>
                            <span class="badge {{ $row['e14_estado'] === 'Sí' ? 'badge-success' : 'badge-secondary' }}">{{ $row['e14_estado'] }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $row['control_estado'] === 'Completo' ? 'badge-success' : 'badge-warning' }}">{{ $row['control_estado'] }}</span>
                        </td>
                        <td>
                            @php $cls = $row['balance_alerta'] === 'Alerta' ? 'badge-danger' : ($row['balance_alerta'] === 'OK' ? 'badge-success' : 'badge-secondary'); @endphp
                            <span class="badge {{ $cls }}">{{ $row['balance_alerta'] }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $row['reclamacion_alerta'] === 'Alerta' ? 'badge-danger' : 'badge-success' }}">{{ $row['reclamacion_alerta'] }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $row['reconteo_alerta'] === 'Alerta' ? 'badge-danger' : 'badge-success' }}">{{ $row['reconteo_alerta'] }}</span>
                        </td>
                        <td>
                            @php $cls = $row['jurados_alerta'] === 'Alerta' ? 'badge-danger' : ($row['jurados_alerta'] === 'OK' ? 'badge-success' : 'badge-secondary'); @endphp
                            <span class="badge {{ $cls }}">{{ $row['jurados_alerta'] }}</span>
                        </td>
                        <td>
                            <a href="{{ route('admin.mesa_reportes.mesas.show', ['mesa' => $row['mesa_id'], 'eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">Ver</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@stop

@push('js')
<script>
$(function () {
    $('#alertasMesasTable').DataTable({
        responsive: true,
        scrollX: true,
        pageLength: 50,
        order: [[0, 'asc'], [1, 'asc'], [2, 'asc'], [3, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        }
    });
});
</script>
@endpush
