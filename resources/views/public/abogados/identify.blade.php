@extends('public.abogados.layout')

@section('title', 'Actualizar información')
@section('heading', 'Actualización de datos')
@section('subtitle', 'Ingresa tu cédula para acceder de forma segura a tu información.')

@section('content')
    <form method="POST" action="{{ route('public.abogados.update.lookup', $accessToken->token) }}">
        @csrf
        <div class="form-group">
            <label for="cc">Número de cédula</label>
            <input type="text" name="cc" id="cc" class="form-control form-control-lg" value="{{ old('cc') }}" autocomplete="off" required>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit">Continuar</button>
        <p class="text-muted small mt-3 mb-0">La cédula se valida junto con el enlace temporal. No compartiremos información sin esta comprobación.</p>
    </form>
@stop
