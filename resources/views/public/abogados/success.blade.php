@extends('public.abogados.layout')

@section('title', $title)
@section('heading', $title)
@section('subtitle', 'Proceso finalizado correctamente.')

@section('content')
    <div class="text-center py-5">
        <div class="display-4 text-success mb-3"><i class="fas fa-check-circle"></i></div>
        <h3>{{ $message }}</h3>
        <p class="text-muted mb-0">Ya puedes cerrar esta ventana.</p>
    </div>
@stop
