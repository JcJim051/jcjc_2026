<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asistencia Comité Electoral | Testiapp</title>
    <link rel="icon" href="{{ asset('favicons/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/testiapp-tokens.css') }}">
    <style>
        .attendance-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(320px, .85fr) minmax(440px, 1.15fr);
            align-items: center;
            gap: clamp(30px, 5vw, 78px);
            padding: clamp(26px, 5vw, 72px);
        }
        .attendance-copy { max-width: 650px; }
        .attendance-copy h1 {
            margin: 14px 0 20px;
            font-size: clamp(42px, 5.2vw, 76px);
            line-height: .98;
            letter-spacing: -.055em;
        }
        .attendance-copy > p {
            color: var(--ta-muted);
            font-size: clamp(19px, 2vw, 27px);
            line-height: 1.45;
        }
        .event-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 28px;
        }
        .event-item {
            padding: 13px 15px;
            border: 1px solid var(--ta-line);
            border-radius: 13px;
            background: rgba(255,255,255,.78);
        }
        .event-item small { display: block; color: var(--ta-muted); font-weight: 800; text-transform: uppercase; }
        .event-item strong { display: block; margin-top: 4px; }
        .qr-card {
            width: min(700px, 100%);
            justify-self: center;
            padding: clamp(20px, 3vw, 38px);
            border-radius: 34px;
            background: #fff;
            box-shadow: 0 30px 90px rgba(20, 33, 42, .18);
        }
        .qr-card svg { display: block; width: 100%; height: auto; }
        .qr-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-top: 18px;
            border-top: 1px solid var(--ta-line);
            font-weight: 900;
        }
        .countdown { color: var(--ta-brand-dark); }
        @media (max-width: 850px) {
            .attendance-page { grid-template-columns: 1fr; padding: 28px 14px; text-align: center; }
            .attendance-copy { margin: auto; }
            .token-brand { justify-content: center; }
            .event-grid { text-align: left; }
        }
    </style>
</head>
<body>
    <main class="attendance-page">
        <section class="attendance-copy">
            <div class="token-brand">
                <img src="{{ asset('img/logo.png') }}" alt="Testiapp">
                <span>Testiapp</span>
            </div>
            <div style="color:var(--ta-brand-dark);font-weight:900;letter-spacing:.12em;text-transform:uppercase;">Registro de asistencia</div>
            <h1>Comité Electoral</h1>
            <p>Escanea el código con tu celular y confirma tu asistencia.</p>
            <div class="event-grid">
                <div class="event-item"><small>Lugar</small><strong>{{ $session->reunion->lugar ?: 'Por confirmar' }}</strong></div>
                <div class="event-item"><small>Fecha</small><strong>{{ $session->reunion->fecha ?: 'Por confirmar' }}</strong></div>
                <div class="event-item"><small>Horario</small><strong>{{ $session->reunion->hora_inicio ?: '--:--' }} - {{ $session->reunion->hora_fin ?: '--:--' }}</strong></div>
                <div class="event-item"><small>Registrados</small><strong>{{ $asistentesRegistrados }} asistentes</strong></div>
            </div>
        </section>

        <section class="qr-card">
            {!! $qrSvg !!}
            <div class="qr-meta">
                <span>Escanea para ingresar</span>
                <span class="countdown">Cambia en <span id="countdown">30</span>s</span>
            </div>
        </section>
    </main>

    <script>
        let seconds = 30;
        const countdown = document.getElementById('countdown');
        const timer = setInterval(function () {
            seconds -= 1;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
    </script>
</body>
</html>
