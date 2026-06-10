@extends('public.abogados.layout')

@section('title', 'Actualizar información')
@section('heading', 'Actualiza tu información')
@section('subtitle', 'Verifica los datos actuales y modifica únicamente lo necesario.')

@section('content')
    <form method="POST" action="{{ route('public.abogados.update.store', $accessToken->token) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('public.abogados._form', ['creating' => false])
        <div class="text-right mt-3">
            <button class="btn btn-success btn-lg" type="submit">Guardar actualización</button>
        </div>
    </form>
@stop

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
@stop
@section('js')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>$(function () { $('.js-puesto').select2({ width: '100%', placeholder: 'Seleccione...' }); });</script>
@stop
