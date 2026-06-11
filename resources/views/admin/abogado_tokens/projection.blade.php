<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>QR {{ $token->label ?: ('Enlace #' . $token->id) }} | Testiapp</title>
    <link rel="icon" href="{{ asset('favicons/favicon.png') }}">
    <style>
        :root {
            --ink: #14212a;
            --muted: #66747d;
            --brand: #1097ad;
            --brand-dark: #087387;
            --accent: #f5b51b;
            --paper: #ffffff;
            --success: #16865d;
            --danger: #bf3f48;
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 5% 10%, rgba(16, 151, 173, .22), transparent 28rem),
                radial-gradient(circle at 95% 90%, rgba(245, 181, 27, .18), transparent 24rem),
                #edf4f5;
        }
        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(310px, .78fr) minmax(440px, 1.22fr);
            padding: clamp(22px, 4vw, 64px);
            gap: clamp(28px, 5vw, 76px);
            align-items: center;
        }
        .info { max-width: 650px; }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: clamp(36px, 7vh, 82px);
            color: var(--brand-dark);
            font-size: clamp(18px, 2vw, 25px);
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .brand img { width: 52px; height: 52px; object-fit: contain; }
        .eyebrow {
            color: var(--brand-dark);
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        h1 {
            margin: 12px 0 22px;
            font-size: clamp(38px, 5.2vw, 78px);
            line-height: .98;
            letter-spacing: -.055em;
        }
        .instruction {
            max-width: 600px;
            margin: 0;
            color: var(--muted);
            font-size: clamp(19px, 2.1vw, 29px);
            line-height: 1.45;
        }
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 32px;
        }
        .pill {
            padding: 10px 15px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .76);
            border: 1px solid rgba(20, 33, 42, .09);
            font-size: 15px;
            font-weight: 800;
        }
        .pill.available { color: var(--success); }
        .pill.unavailable { color: var(--danger); }
        .qr-panel {
            width: min(720px, 100%);
            justify-self: center;
            position: relative;
            padding: clamp(20px, 3vw, 38px);
            border-radius: 34px;
            background: var(--paper);
            box-shadow: 0 30px 90px rgba(20, 33, 42, .18);
        }
        .qr-panel::before {
            content: "";
            position: absolute;
            inset: -8px;
            z-index: -1;
            border-radius: 42px;
            background: linear-gradient(145deg, var(--brand), var(--accent));
        }
        .qr {
            display: grid;
            place-items: center;
            aspect-ratio: 1;
        }
        .qr svg { display: block; width: 100%; height: 100%; }
        .qr-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding-top: 18px;
            border-top: 1px solid #e6edef;
        }
        .link-type { font-weight: 900; }
        .countdown { color: var(--muted); font-weight: 700; text-align: right; }
        .tools {
            position: fixed;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .tool {
            border: 1px solid rgba(20, 33, 42, .12);
            border-radius: 10px;
            padding: 10px 14px;
            color: var(--ink);
            background: rgba(255, 255, 255, .9);
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
        }
        @media (max-width: 850px) {
            .page { grid-template-columns: 1fr; text-align: center; padding-top: 76px; }
            .info { margin: auto; }
            .brand, .meta { justify-content: center; }
            .brand { margin-bottom: 28px; }
            .qr-panel { max-width: 580px; }
        }
        @media print {
            .tools { display: none; }
            body { background: #fff; }
            .page { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="tools">
        <a class="tool" href="{{ route('admin.abogado_tokens.index') }}">Volver</a>
        <button class="tool" type="button" onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()">Pantalla completa</button>
    </div>

    <main class="page">
        <section class="info">
            <div class="brand">
                <img src="{{ asset('img/logo.png') }}" alt="Testiapp">
                <span>Testiapp</span>
            </div>
            <div class="eyebrow">
                {{ $token->type === 'characterization' ? 'Nueva caracterización' : 'Actualización de información' }}
            </div>
            <h1>{{ $token->label ?: 'Equipo de abogados' }}</h1>
            <p class="instruction">
                Escanea el código con la cámara de tu celular y completa el formulario.
            </p>
            <div class="meta">
                <span class="pill {{ $token->isAvailable() ? 'available' : 'unavailable' }}">
                    {{ $token->isAvailable() ? 'Enlace disponible' : 'Enlace no disponible' }}
                </span>
                <span class="pill">Registros completados: {{ $token->completed_count }}</span>
            </div>
        </section>

        <section class="qr-panel">
            <div class="qr">{!! $qrSvg !!}</div>
            <div class="qr-footer">
                <div class="link-type">
                    {{ $token->type === 'characterization' ? 'Caracterización' : 'Actualizar datos' }}
                </div>
                <div class="countdown" id="expiryText">
                    Válido hasta {{ optional($token->expires_at)->format('d/m/Y h:i A') }}
                </div>
            </div>
        </section>
    </main>

    <script>
        (function () {
            const expiry = new Date(@json(optional($token->expires_at)->toIso8601String()));
            const output = document.getElementById('expiryText');

            function updateCountdown() {
                const difference = expiry.getTime() - Date.now();
                if (difference <= 0) {
                    output.textContent = 'Este enlace ha vencido';
                    return;
                }

                const hours = Math.floor(difference / 3600000);
                const minutes = Math.floor((difference % 3600000) / 60000);
                const seconds = Math.floor((difference % 60000) / 1000);
                output.textContent = 'Vence en ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        })();
    </script>
</body>
</html>
