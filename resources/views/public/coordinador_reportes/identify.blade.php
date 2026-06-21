@extends('public.coordinador_reportes.layout')
@section('title', 'Ingreso coordinador')
@section('brand_title', 'Reporte de Afluencia y E14')
@section('brand_subtitle', 'Elección: ' . ($eleccion->nombre ?? 'N/D'))
@section('content_body')
<div class="row justify-content-center"><div class="col-lg-6"><div class="card soft-card"><div class="card-header">Identifica al coordinador</div><div class="card-body"><form method="POST" action="{{ route('public.coordinador_reportes.lookup', $token->token) }}">@csrf<div class="form-group"><label>Cédula del coordinador</label><input type="text" name="cedula" class="form-control form-control-lg" value="{{ old('cedula') }}" required autofocus><small class="form-text text-muted">El sistema validará la coordinación activa dentro del alcance de este link.</small></div><button class="btn btn-primary btn-lg btn-block" type="submit">Consultar mis puestos</button></form></div></div></div></div>
@endsection
