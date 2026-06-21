@extends('public.coordinador_reportes.layout')
@section('title', 'Comisión no disponible')
@section('brand_title', 'Comisión de Alertas Cerrada')
@section('brand_subtitle', 'El enlace ya no está disponible para consulta.')
@section('content_body')
<div class="text-center py-4">
    <h4>No se puede usar este link ahora</h4>
    <p class="text-muted mb-0">Verifica con el equipo operativo si el token fue desactivado o si ya venció.</p>
</div>
@endsection
