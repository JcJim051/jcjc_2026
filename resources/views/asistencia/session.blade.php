@extends('public.abogados.layout')

@section('title', 'Asistencia Comité Electoral')
@section('heading', 'Asistencia Comité Electoral')
@section('subtitle')
    Confirma tu identidad y completa tu celular si todavía no está registrado.
@stop

@section('content')
    <div class="mb-4">
        <strong>{{ $session->reunion->lugar ?: 'Lugar pendiente' }}</strong>
        <div class="text-muted">
            {{ $session->reunion->fecha ?: 'Fecha pendiente' }}
            · {{ $session->reunion->hora_inicio ?: '--:--' }}–{{ $session->reunion->hora_fin ?: '--:--' }}
        </div>
    </div>

    @if (session('info'))
        <div class="alert alert-success">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <form method="post" action="{{ route('asistencia.reunion.submit', $publicToken) }}">
        @csrf
        <input type="hidden" name="slot" value="{{ $slot }}">
        <input type="hidden" name="token" value="{{ $hashToken }}">

        <div class="section-title">Validación de identidad</div>
        <p class="text-muted mb-4">
            Validaremos tu cédula y correo. Si aún no tienes celular registrado, el número ingresado se agregará
            automáticamente a tu caracterización.
        </p>

        <div class="row">
            <div class="col-md-4 form-group">
                <label for="cc">Cédula</label>
                <input class="form-control" type="text" id="cc" name="cc" value="{{ old('cc') }}" autocomplete="off" required>
            </div>
            <div class="col-md-4 form-group">
                <label for="correo">Correo registrado</label>
                <input class="form-control" type="email" id="correo" name="correo" value="{{ old('correo') }}" autocomplete="email" required>
            </div>
            <div class="col-md-4 form-group">
                <label for="telefono">Celular</label>
                <input class="form-control" type="text" id="telefono" name="telefono" value="{{ old('telefono') }}" autocomplete="tel" required>
                <small class="form-text text-muted">
                    Si ya tienes celular registrado debe coincidir. Si no tienes, este será guardado en tu caracterización.
                </small>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block mt-3">
            Registrar asistencia
        </button>
    </form>
@stop
