@extends('adminlte::page')
@section('title', 'Indicadores E14')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <div>
        <h1 class="mb-1">Indicadores E14 y Reclamaciones</h1>
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
@include('admin.mesa_reportes.partials.kpis')

<div class="row mb-3">
    <div class="col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-file-signature"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">E14 guardados</span>
                <span class="info-box-number">{{ $kpis['mesas_e14'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-retweet"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Con reconteo</span>
                <span class="info-box-number">{{ $kpis['mesas_reconteo'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Con reclamación</span>
                <span class="info-box-number">{{ $kpis['mesas_reclamacion'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-camera"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Fotos cargadas</span>
                <span class="info-box-number">{{ $rows->whereNotNull('reclamacion_foto_path')->count() }}</span>
            </div>
        </div>
    
</div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Villavicencio por comuna</h3></div>
            <div class="card-body" style="height: 380px;">
                @if(collect($chartE14VillavicencioComunas ?? [])->isNotEmpty())
                    <canvas id="chartE14VillavicencioComunas"></canvas>
                @else
                    <div class="d-flex align-items-center justify-content-center h-100 text-muted text-center px-3">
                        No hay datos disponibles para la grafica de Villavicencio con los filtros actuales.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Otros municipios</h3></div>
            <div class="card-body" style="height: 380px;">
                @if(collect($chartE14Municipios ?? [])->isNotEmpty())
                    <canvas id="chartE14Municipios"></canvas>
                @else
                    <div class="d-flex align-items-center justify-content-center h-100 text-muted text-center px-3">
                        No hay datos disponibles para la grafica de otros municipios con los filtros actuales.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <h3 class="card-title mb-0">Resumen por comuna</h3>
        @can('dashboard-operativo-gestionar')
        <a href="{{ route('admin.mesa_reportes.e14.export_comunas', ['eleccion_id' => $eleccionId, 'municipio' => $filtroMunicipio, 'puesto_id' => $filtroPuestoId, 'coordinador_id' => $filtroCoordinadorId]) }}" class="btn btn-success btn-sm">
            Descargar Excel
        </a>
        @endcan
    </div>
    <div class="card-body table-responsive">
        <table id="e14ComunasTable" class="table table-bordered table-sm display nowrap mb-0" style="width:100%">
            <thead>
                <tr>
                    <th>Municipio</th>
                    <th>Comuna</th>
                    <th>Mesas total</th>
                    <th>E14</th>
                    <th>Control</th>
                    <th>Reconteos</th>
                    <th>Reclamaciones</th>
                    <th>Fotos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenComunaE14 as $row)
                    <tr>
                        <td>{{ $row['municipio'] }}</td>
                        <td>{{ $row['comuna'] }}</td>
                        <td>{{ $row['mesas_total'] }}</td>
                        <td>{{ $row['e14'] }}</td>
                        <td>{{ $row['control'] }}</td>
                        <td>{{ $row['reconteos'] }}</td>
                        <td>{{ $row['reclamaciones'] }}</td>
                        <td>{{ $row['fotos'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <h3 class="card-title mb-0">Detalle por puesto</h3>
        @can('dashboard-operativo-gestionar')
        <a href="{{ route('admin.mesa_reportes.e14.export_puestos', ['eleccion_id' => $eleccionId, 'municipio' => $filtroMunicipio, 'puesto_id' => $filtroPuestoId, 'coordinador_id' => $filtroCoordinadorId]) }}" class="btn btn-success btn-sm">
            Descargar Excel
        </a>
        @endcan
    </div>
    <div class="card-body table-responsive">
        <table id="e14PuestosTable" class="table table-bordered table-sm display nowrap mb-0" style="width:100%">
            <thead>
                <tr>
                    <th>Municipio</th>
                    <th>Comuna</th>
                    <th>Puesto</th>
                    <th>Mesas total</th>
                    <th>E14</th>
                    <th>Control</th>
                    <th>Avance %</th>
                    <th>Reconteos</th>
                    <th>Reclamaciones</th>
                    <th>Fotos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenPuestoE14 as $row)
                    <tr>
                        <td>{{ $row['municipio'] }}</td>
                        <td>{{ $row['comuna'] }}</td>
                        <td>{{ $row['puesto'] }}</td>
                        <td>{{ $row['mesas_total'] }}</td>
                        <td>{{ $row['e14'] }}</td>
                        <td>{{ $row['control'] }}</td>
                        <td>{{ number_format($row['pct_e14'], 1) }}%</td>
                        <td>{{ $row['reconteos'] }}</td>
                        <td>{{ $row['reclamaciones'] }}</td>
                        <td>{{ $row['fotos'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <h3 class="card-title mb-0">Detalle E14 por mesa</h3>
        @can('dashboard-operativo-gestionar')
        <a href="{{ route('admin.mesa_reportes.e14.export_mesas', ['eleccion_id' => $eleccionId, 'municipio' => $filtroMunicipio, 'puesto_id' => $filtroPuestoId, 'coordinador_id' => $filtroCoordinadorId]) }}" class="btn btn-success btn-sm">
            Descargar Excel
        </a>
        @endcan
    </div>
    <div class="card-body table-responsive">
        <table id="e14MesasTable" class="table table-bordered table-sm display nowrap mb-0" style="width:100%">
            <thead>
                <tr>
                    <th>Municipio</th>
                    <th>Comuna</th>
                    <th>Puesto</th>
                    <th>Mesa</th>
                    <th>Coordinador</th>
                    <th>E14</th>
                    <th>Reconteo</th>
                    <th>Reclamación</th>
                    <th>Jurados firmaron</th>
                    <th>Control</th>
                    <th>Foto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->municipio }}</td>
                        <td>{{ trim((string)($row->comuna ?? '')) !== '' ? $row->comuna : 'SIN COMUNA' }}</td>
                        <td>{{ $row->puesto }}</td>
                        <td>{{ $row->mesa_num }}</td>
                        <td>{{ $row->coordinador_nombre ?: 'N/D' }}</td>
                        <td>{{ $row->e14_reportado_at ? 'Sí' : 'No' }}</td>
                        <td>{{ (int) $row->reconteo === 1 ? 'Sí' : 'No' }}</td>
                        <td>
                            @if((int) $row->reclamacion === 1)
                                {{ $row->reclamacion_origen === 'nuestra' ? 'Nuestra' : 'Otro candidato' }}
                            @else
                                No
                            @endif
                        </td>
                        <td>{{ $row->jurados_firmaron ?? '-' }}</td>
                        <td>{{ $row->control_final_at ? 'Completo' : 'Pendiente' }}</td>
                        <td>{{ !empty($row->reclamacion_foto_path) ? 'Sí' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@stop

@push('js')
<script src="{{ asset('vendor/chart.js/Chart.bundle.min.js') }}"></script>
<script>
(function(){
    var villavo = @json($chartE14VillavicencioComunas ?? []);
    var otros = @json($chartE14Municipios ?? []);

    function buildE14BarChart(canvasId, rows, labelKey) {
        var el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined' || !rows.length) return;

        new Chart(el.getContext('2d'), {
            type: 'bar',
            data: {
                labels: rows.map(function (row) { return row[labelKey]; }),
                datasets: [
                    {
                        label: 'Mesas total',
                        backgroundColor: '#34495e',
                        data: rows.map(function (row) { return Number(row.mesas_total || 0); })
                    },
                    {
                        label: 'Mesas reportadas',
                        backgroundColor: '#18a558',
                        data: rows.map(function (row) { return Number(row.e14 || 0); })
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                legend: { position: 'bottom' },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        afterBody: function (tooltipItems) {
                            if (!tooltipItems.length) return '';
                            var row = rows[tooltipItems[0].index] || {};
                            return '% avance: ' + Number(row.pct_e14 || 0).toFixed(1) + '%';
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        stacked: false,
                        ticks: { autoSkip: false, fontSize: 10 }
                    }],
                    yAxes: [{
                        ticks: { beginAtZero: true },
                        scaleLabel: { display: true, labelString: 'Mesas' }
                    }]
                }
            }
        });
    }

    buildE14BarChart('chartE14VillavicencioComunas', villavo, 'comuna');
    buildE14BarChart('chartE14Municipios', otros, 'municipio');
    $('#e14ComunasTable').DataTable({
        responsive: true,
        scrollX: true,
        pageLength: 25,
        order: [[0, 'asc'], [1, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        }
    });

    $('#e14PuestosTable').DataTable({
        responsive: true,
        scrollX: true,
        pageLength: 25,
        order: [[0, 'asc'], [1, 'asc'], [2, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        }
    });

    $('#e14MesasTable').DataTable({
        responsive: true,
        scrollX: true,
        pageLength: 50,
        order: [[0, 'asc'], [1, 'asc'], [2, 'asc'], [3, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        }
    });
})();
</script>
@endpush
