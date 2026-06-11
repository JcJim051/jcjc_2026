<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>QR {{ $target === 'seguimiento' ? 'Avance' : 'Referidos' }} | Testiapp</title>
    <link rel="icon" href="{{ asset('favicons/favicon.png') }}">
    <style>
        :root {
            --ink: #14212a;
            --muted: #66747d;
            --brand: #1097ad;
            --brand-dark: #087387;
            --accent: #f5b51b;
            --paper: #fff;
            --success: #16865d;
            --danger: #bf3f48;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 5% 10%, rgba(16,151,173,.22), transparent 28rem),
                radial-gradient(circle at 95% 90%, rgba(245,181,27,.18), transparent 24rem),
                #edf4f5;
        }
        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(320px,.82fr) minmax(440px,1.18fr);
            align-items: center;
            gap: clamp(28px,5vw,76px);
            padding: clamp(22px,4vw,64px);
        }
        .info { max-width: 670px; }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: clamp(34px,7vh,78px);
            color: var(--brand-dark);
            font-size: clamp(18px,2vw,25px);
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .brand img { width: 52px; height: 52px; object-fit: contain; }
        .eyebrow { color: var(--brand-dark); font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 12px 0 20px; font-size: clamp(38px,5.1vw,76px); line-height: .98; letter-spacing: -.055em; }
        .instruction { margin: 0; color: var(--muted); font-size: clamp(19px,2vw,28px); line-height: 1.45; }
        .territory {
            margin-top: 26px;
            padding: 16px 18px;
            border: 1px solid rgba(20,33,42,.09);
            border-radius: 15px;
            background: rgba(255,255,255,.75);
        }
        .territory strong { display: block; font-size: 18px; }
        .territory span { color: var(--muted); }
        .pills { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 18px; }
        .pill { padding: 9px 14px; border: 1px solid rgba(20,33,42,.09); border-radius: 999px; background: rgba(255,255,255,.76); font-weight: 800; }
        .pill.available { color: var(--success); }
        .pill.unavailable { color: var(--danger); }
        .qr-panel {
            width: min(720px,100%);
            justify-self: center;
            position: relative;
            padding: clamp(20px,3vw,38px);
            border-radius: 34px;
            background: var(--paper);
            box-shadow: 0 30px 90px rgba(20,33,42,.18);
        }
        .qr-panel::before {
            content: "";
            position: absolute;
            inset: -8px;
            z-index: -1;
            border-radius: 42px;
            background: linear-gradient(145deg,var(--brand),var(--accent));
        }
        .qr svg { display: block; width: 100%; height: auto; }
        .qr-footer {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding-top: 18px;
            border-top: 1px solid #e6edef;
            font-weight: 900;
        }
        .expiry { color: var(--muted); text-align: right; }
        .tools { position: fixed; top: 16px; right: 16px; display: flex; gap: 8px; z-index: 10; }
        .tool { padding: 10px 14px; color: var(--ink); border: 1px solid rgba(20,33,42,.12); border-radius: 10px; background: rgba(255,255,255,.92); font: inherit; font-weight: 800; cursor: pointer; text-decoration: none; }
        @media (max-width:850px) {
            .page { grid-template-columns: 1fr; padding: 76px 14px 28px; text-align: center; }
            .info { margin: auto; }
            .brand,.pills { justify-content: center; }
            .brand { margin-bottom: 28px; }
            .territory { text-align: left; }
        }
    </style>
</head>
<body>
    <div class="tools">
        <a class="tool" href="{{ route('admin.territorio_tokens.index', ['eleccion_id' => $token->eleccion_id]) }}">Volver</a>
        <button class="tool" type="button" onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()">Pantalla completa</button>
    </div>

    <main class="page">
        <section class="info">
            <div class="brand"><img src="{{ asset('img/logo.png') }}" alt="Testiapp"><span>Testiapp</span></div>
            <div class="eyebrow">{{ $target === 'seguimiento' ? 'Consulta de avance' : 'Referir testigo' }}</div>
            <h1>{{ $token->responsable ?: ($target === 'seguimiento' ? 'Avance territorial' : 'Equipo territorial') }}</h1>
            <p class="instruction">
                {{ $target === 'seguimiento'
                    ? 'Escanea el código para consultar el avance y detalle de cobertura.'
                    : 'Escanea el código con tu celular y registra un nuevo testigo electoral.' }}
            </p>
            <div class="territory">
                <strong>{{ $territorio->municipio ?? 'Territorio asignado' }}</strong>
                <span>{{ $territorio->departamento ?? '' }}{{ $token->comuna ? ' · ' . $token->comuna : '' }}</span>
            </div>
            <div class="pills">
                <span class="pill {{ $token->activo ? 'available' : 'unavailable' }}">{{ $token->activo ? 'Token activo' : 'Token inactivo' }}</span>
                @if($eleccion)<span class="pill">{{ $eleccion->nombre }}</span>@endif
            </div>
        </section>

        <section class="qr-panel">
            <div class="qr">{!! $qrSvg !!}</div>
            <div class="qr-footer">
                <span>{{ $target === 'seguimiento' ? 'Ver seguimiento' : 'Abrir formulario' }}</span>
                <span class="expiry" id="expiryText">
                    {{ $token->expires_at ? 'Válido hasta ' . $token->expires_at->format('d/m/Y') : 'Sin fecha de vencimiento' }}
                </span>
            </div>
        </section>
    </main>

    @if($token->expires_at)
        <script>
            (function () {
                const expiry = new Date(@json($token->expires_at->toIso8601String()));
                const output = document.getElementById('expiryText');
                function update() {
                    const difference = expiry.getTime() - Date.now();
                    if (difference <= 0) { output.textContent = 'Este token ha vencido'; return; }
                    const days = Math.floor(difference / 86400000);
                    const hours = Math.floor((difference % 86400000) / 3600000);
                    const minutes = Math.floor((difference % 3600000) / 60000);
                    output.textContent = 'Vence en ' + days + 'd ' + hours + 'h ' + minutes + 'm';
                }
                update();
                setInterval(update, 60000);
            })();
        </script>
    @endif
</body>
</html>
