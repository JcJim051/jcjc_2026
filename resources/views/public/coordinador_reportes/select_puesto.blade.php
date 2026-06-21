@extends('public.coordinador_reportes.layout')
@section('title', 'Seleccionar puesto')
@section('brand_title', 'Selecciona el puesto a reportar')
@section('brand_subtitle', ($abogado->nombre ?? 'Coordinador') . ' | Elección: ' . ($eleccion->nombre ?? 'N/D'))
@section('content_body')
<div class="card soft-card"><div class="card-header">Puestos coordinados</div><div class="card-body"><form method="POST" action="{{ route('public.coordinador_reportes.select_puesto', $token->token) }}">@csrf<div class="row">@foreach($puestos as $puesto)<div class="col-md-6 mb-3"><label class="d-block"><div class="border rounded p-3 h-100"><div class="custom-control custom-radio"><input class="custom-control-input" type="radio" name="puesto_id" id="puesto_{{ $puesto->puesto_id }}" value="{{ $puesto->puesto_id }}" required><label class="custom-control-label font-weight-bold" for="puesto_{{ $puesto->puesto_id }}">{{ $puesto->puesto }}</label></div><div class="text-muted mt-2">{{ $puesto->municipio }} | Comuna: {{ $puesto->comuna ?: 'N/D' }}</div></div></label></div>@endforeach</div><button class="btn btn-primary" type="submit">Continuar con mesas</button></form></div></div>
@endsection
