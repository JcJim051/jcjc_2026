@extends('public.abogados.layout')

@section('title', 'Caracterización de abogados')
@section('heading', 'Únete al equipo electoral')
@section('subtitle', 'Completa tu caracterización para iniciar tu vinculación con el equipo.')

@section('content')
    <form method="POST" action="{{ route('public.abogados.characterization.store', $accessToken->token) }}" enctype="multipart/form-data">
        @csrf
        @include('public.abogados._form', ['creating' => true, 'abogado' => null])
        <div class="text-right mt-3">
            <button class="btn btn-success btn-lg" type="submit">Enviar caracterización</button>
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
