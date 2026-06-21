@extends('public.coordinador_reportes.layout')
@section('title', 'Mesas del puesto')
@section('brand_title', 'Mesas del puesto coordinado')
@section('brand_subtitle', ($abogado->nombre ?? 'Coordinador') . ' | ' . ($puesto->municipio ?? 'N/D') . ' - ' . ($puesto->puesto ?? 'Puesto'))
@section('content_body')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
    <div>
        <div><strong>Elección:</strong> {{ $eleccion->nombre ?? 'N/D' }}</div>
        <div><strong>Comuna:</strong> {{ $puesto->comuna ?: 'N/D' }}</div>
    </div>
    <a href="{{ route('public.coordinador_reportes.identify', $token->token) }}" class="btn btn-outline-primary">Cambiar coordinador</a>
</div>
<div class="row mb-3">
    <div class="col-md-4 mb-2"><div class="metric-box"><div class="label">Mesas del puesto</div><div class="value">{{ $mesas->count() }}</div></div></div>
    <div class="col-md-4 mb-2"><div class="metric-box"><div class="label">Afluencia completa</div><div class="value">{{ !empty($flujo['afluencia']) ? $mesas->where('afluencia_completa', true)->count() : 'Off' }}</div></div></div>
    <div class="col-md-4 mb-2"><div class="metric-box"><div class="label">E14 reportados</div><div class="value">{{ !empty($flujo['e14_link']) ? $mesas->whereNotNull('e14_reportado_at')->count() : 'Off' }}</div></div></div>
</div>
<div class="card soft-card">
    <div class="card-header">Selecciona una mesa</div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-sm mb-0">
            <thead>
                <tr>
                    <th>Mesa</th>
                    <th>Afluencia</th>
                    <th>E14</th>
                    <th>Control final</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mesas as $mesa)
                    <tr>
                        <td><strong>{{ $mesa->mesa_num }}</strong></td>
                        <td>
                            @if($mesa->afluencia_completa)
                                <span class="badge-soft badge-ok">Completa</span>
                            @elseif(!is_null($mesa->afluencia_9) || !is_null($mesa->afluencia_11) || !is_null($mesa->afluencia_14))
                                <span class="badge-soft badge-pending">Parcial</span>
                            @else
                                <span class="badge-soft badge-empty">Sin reporte</span>
                            @endif
                        </td>
                        <td>
                            @if($mesa->e14_reportado_at)
                                <span class="badge-soft badge-ok">Guardado</span>
                            @else
                                <span class="badge-soft badge-empty">Pendiente</span>
                            @endif
                        </td>
                        <td>
                            @if($mesa->control_final_at)
                                <span class="badge-soft badge-ok">Completo</span>
                            @else
                                <span class="badge-soft badge-empty">Pendiente</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-wrap" style="gap:8px;">
                                @if(!empty($flujo['afluencia']))
                                    <a href="{{ route('public.coordinador_reportes.mesa_afluencia', [$token->token, $mesa->id]) }}" class="btn btn-primary btn-sm">Afluencia</a>
                                @endif
                                @if(!empty($flujo['e14_link']))
                                    <a href="{{ route('public.coordinador_reportes.mesa_e14', [$token->token, $mesa->id]) }}" class="btn btn-outline-primary btn-sm">E14</a>
                                @endif
                                @if(empty($flujo['afluencia']) && empty($flujo['e14_link']))
                                    <span class="badge-soft badge-empty">Sin pasos activos</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
