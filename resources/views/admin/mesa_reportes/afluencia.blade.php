@extends('adminlte::page')
@section('title', 'Indicadores de Afluencia')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;"><div><h1 class="mb-1">Indicadores de Afluencia</h1>@if($eleccionOperativa)<div class="text-muted">Elección operativa: <strong>{{ $eleccionOperativa->nombre }}</strong></div>@endif</div><div class="d-flex" style="gap:8px;"><a href="{{ route('admin.mesa_reportes.dashboard', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">Dashboard</a><a href="{{ route('admin.mesa_reportes.afluencia', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">Afluencia</a><a href="{{ route('admin.mesa_reportes.e14', ['eleccion_id' => $eleccionId]) }}" class="btn btn-outline-primary btn-sm">E14</a></div></div>
@stop
@section('content')
@include('admin.mesa_reportes.partials.filters')
@include('admin.mesa_reportes.partials.step_toggles')
@include('admin.mesa_reportes.partials.kpis')
<div class="row mb-3"><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Personas 9:00</span><span class="info-box-number">{{ number_format($kpis['personas_afluencia_9']) }}</span><small>Mesas con reporte: {{ $kpis['mesas_afluencia_9'] }}</small></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Personas 11:00</span><span class="info-box-number">{{ number_format($kpis['personas_afluencia_11']) }}</span><small>Mesas con reporte: {{ $kpis['mesas_afluencia_11'] }}</small></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Personas 2:00</span><span class="info-box-number">{{ number_format($kpis['personas_afluencia_14']) }}</span><small>Mesas con reporte: {{ $kpis['mesas_afluencia_14'] }}</small></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-layer-group"></i></span><div class="info-box-content"><span class="info-box-text">Total personas</span><span class="info-box-number">{{ number_format($kpis['personas_afluencia_total']) }}</span><small>3 cortes completos: {{ $kpis['mesas_afluencia_completa'] }}</small></div></div></div></div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Villavicencio por comuna</h3></div>
            <div class="card-body" style="height: 380px;">
                <canvas id="chartVillavicencioComunas"></canvas>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Otros municipios</h3></div>
            <div class="card-body" style="height: 380px;">
                <canvas id="chartOtrosMunicipios"></canvas>
            </div>
        </div>
    </div>
</div>
@php
    $max9 = max(1, (int) $resumenComunaAfluencia->max('personas_9'));
    $max11 = max(1, (int) $resumenComunaAfluencia->max('personas_11'));
    $max14 = max(1, (int) $resumenComunaAfluencia->max('personas_14'));
    $maxTotal = max(1, (int) $resumenComunaAfluencia->max('personas_total'));
    $heatClass = function ($value, $max) {
        if ((int) $value <= 0) return 'bg-light';
        $ratio = $max > 0 ? ((int) $value / $max) : 0;
        if ($ratio >= 0.75) return 'bg-danger text-white';
        if ($ratio >= 0.5) return 'bg-warning';
        if ($ratio >= 0.25) return 'bg-info text-white';
        return 'bg-success text-white';
    };
@endphp
<div class="card"><div class="card-header"><h3 class="card-title">Mapa de calor por comuna</h3></div><div class="card-body table-responsive"><table id="afluenciaHeatTable" class="table table-bordered table-sm display nowrap mb-0" style="width:100%"><thead><tr><th>Municipio</th><th>Comuna</th><th>Mesas</th><th>Mesas con reporte</th><th>9:00</th><th>11:00</th><th>2:00</th><th>Total personas</th></tr></thead><tbody>@foreach($resumenComunaAfluencia as $row)<tr><td>{{ $row['municipio'] }}</td><td>{{ $row['comuna'] }}</td><td>{{ $row['mesas_total'] }}</td><td>{{ $row['mesas_con_reporte'] }}</td><td class="{{ $heatClass($row['personas_9'], $max9) }} font-weight-bold">{{ number_format($row['personas_9']) }}</td><td class="{{ $heatClass($row['personas_11'], $max11) }} font-weight-bold">{{ number_format($row['personas_11']) }}</td><td class="{{ $heatClass($row['personas_14'], $max14) }} font-weight-bold">{{ number_format($row['personas_14']) }}</td><td class="{{ $heatClass($row['personas_total'], $maxTotal) }} font-weight-bold">{{ number_format($row['personas_total']) }}</td></tr>@endforeach</tbody></table></div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Detalle por puesto</h3></div><div class="card-body table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Municipio</th><th>Puesto</th><th>Mesas</th><th>Afluencia</th><th>E14</th><th>Cierre</th></tr></thead><tbody>@foreach($resumenPuesto as $row)<tr><td>{{ $row['municipio'] }}</td><td>{{ $row['puesto'] }}</td><td>{{ $row['mesas_total'] }}</td><td>{{ $row['afluencia'] }}</td><td>{{ $row['e14'] }}</td><td>{{ $row['control'] }}</td></tr>@endforeach</tbody></table></div></div>
@stop

@push('js')
<script src="{{ asset('vendor/chart.js/Chart.bundle.min.js') }}"></script>
<script>
(function(){
    var villavo = @json($chartVillavicencio);
    var otros = @json($chartMunicipios);

    function buildBarChart(canvasId, rows, labelKey, title) {
        var el = document.getElementById(canvasId);
        if (!el || typeof Chart === 'undefined') return;

        new Chart(el.getContext('2d'), {
            type: 'bar',
            data: {
                labels: rows.map(function(row){ return row[labelKey]; }),
                datasets: [
                    {
                        label: '9:00',
                        backgroundColor: '#18a558',
                        data: rows.map(function(row){ return Number(row.personas_9 || 0); })
                    },
                    {
                        label: '11:00',
                        backgroundColor: '#17a2b8',
                        data: rows.map(function(row){ return Number(row.personas_11 || 0); })
                    },
                    {
                        label: '2:00',
                        backgroundColor: '#007bff',
                        data: rows.map(function(row){ return Number(row.personas_14 || 0); })
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                legend: { position: 'bottom' },
                title: { display: false, text: title },
                tooltips: { mode: 'index', intersect: false },
                scales: {
                    xAxes: [{
                        stacked: false,
                        ticks: { autoSkip: false, fontSize: 10 }
                    }],
                    yAxes: [{
                        ticks: { beginAtZero: true },
                        scaleLabel: { display: true, labelString: 'Personas' }
                    }]
                }
            }
        });
    }

    buildBarChart('chartVillavicencioComunas', villavo, 'comuna', 'Villavicencio por comuna');
    buildBarChart('chartOtrosMunicipios', otros, 'municipio', 'Otros municipios');

    $('#afluenciaHeatTable').DataTable({
        responsive: true,
        scrollX: true,
        pageLength: 25,
        order: [[0, 'asc'], [1, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        }
    });
})();
</script>
@endpush
