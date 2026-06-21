@extends('public.coordinador_reportes.layout')
@section('title', 'Afluencia mesa ' . $mesa->mesa_num)
@section('brand_title', 'Afluencia - Mesa ' . $mesa->mesa_num)
@section('brand_subtitle', ($mesa->municipio ?? 'N/D') . ' - ' . ($mesa->puesto ?? 'Puesto') . ' | Elección: ' . ($eleccion->nombre ?? 'N/D'))
@section('content_body')
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <div><strong>Coordinador:</strong> {{ $abogado->nombre ?? 'N/D' }}</div>
    <div class="d-flex flex-wrap" style="gap:8px;">
        <a href="{{ route('public.coordinador_reportes.puesto', $token->token) }}" class="btn btn-outline-primary">Volver a mesas</a>
        @if(!empty($flujo['e14_link']))
            <a href="{{ route('public.coordinador_reportes.mesa_e14', [$token->token, $mesa->mesa_id]) }}" class="btn btn-primary">Ir a E14</a>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Reporte de afluencia</div>
            <div class="card-body">
                <form method="POST" action="{{ route('public.coordinador_reportes.afluencia', [$token->token, $mesa->mesa_id]) }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>9:00 AM</label>
                            <input type="number" min="0" name="afluencia_9" class="form-control form-control-lg" value="{{ old('afluencia_9', $reporte->afluencia_9 ?? '') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>11:00 AM</label>
                            <input type="number" min="0" name="afluencia_11" class="form-control form-control-lg" value="{{ old('afluencia_11', $reporte->afluencia_11 ?? '') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>2:00 PM</label>
                            <input type="number" min="0" name="afluencia_14" class="form-control form-control-lg" value="{{ old('afluencia_14', $reporte->afluencia_14 ?? '') }}">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Guardar afluencia</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="card soft-card h-100">
            <div class="card-header">Estado de la mesa</div>
            <div class="card-body">
                <p class="mb-2"><strong>Mesa:</strong> {{ $mesa->mesa_num }}</p>
                <p class="mb-2"><strong>Puesto:</strong> {{ $mesa->puesto }}</p>
                <p class="mb-2"><strong>Afluencia:</strong>
                    @if(!is_null($reporte->afluencia_9 ?? null) || !is_null($reporte->afluencia_11 ?? null) || !is_null($reporte->afluencia_14 ?? null))
                        <span class="badge-soft badge-pending">En progreso</span>
                    @else
                        <span class="badge-soft badge-empty">Sin reporte</span>
                    @endif
                </p>
                <p class="mb-0"><strong>E14:</strong>
                    @if(!empty($reporte) && !empty($reporte->e14_reportado_at))
                        <span class="badge-soft badge-ok">Guardado</span>
                    @else
                        <span class="badge-soft badge-empty">Pendiente</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
