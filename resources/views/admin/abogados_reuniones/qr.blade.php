@extends('adminlte::page')

@section('title', 'QR Asistencia')

@section('content_header')
    <h2>Panel público de asistencia - Reunión #{{ $reunion->id }}</h2>
@stop

@section('content')
    @if (session('info'))
        <div class="alert alert-success">
            <strong>{{ session('info') }}</strong>
        </div>
    @endif

    <div class="card">
        <div class="card-body text-center">
            <p><strong>Lugar:</strong> {{ $reunion->lugar ?: 'Sin lugar definido' }}</p>
            <p><strong>Fecha:</strong> {{ $reunion->fecha ?: 'Sin fecha definida' }}</p>
            <p><strong>Horario:</strong> {{ $reunion->hora_inicio ?: '--:--' }} - {{ $reunion->hora_fin ?: '--:--' }}</p>
            <p><strong>Asistencias registradas:</strong> {{ $asistentesRegistrados }}</p>
            <p class="text-muted">Comparte este enlace para proyectar el QR en la reunión.</p>
            <p class="mb-2"><strong>El QR cambia en:</strong> <span id="countdown">30</span> s</p>

            <div class="alert alert-info mt-3" style="word-break: break-all;">
                {{ $panelUrl }}
            </div>

            <div class="mt-2">
                <a href="{{ $panelUrl }}" target="_blank" class="btn btn-primary">Abrir panel público</a>
            </div>

            <div class="mt-3">
                <img
                    src="https://quickchart.io/qr?size=280&text={{ urlencode($panelUrl) }}"
                    alt="QR panel público"
                    width="280"
                    height="280"
                >
            </div>

            <div class="mt-3">
                <small class="text-muted">Ese QR abre el panel público que rota automáticamente cada 30 segundos.</small>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        var seconds = 30;
        var countdownEl = document.getElementById('countdown');

        var timer = setInterval(function () {
            seconds -= 1;
            countdownEl.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
    </script>
@stop

