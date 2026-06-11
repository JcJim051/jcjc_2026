<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $status }} | Testiapp</title>
    <link rel="icon" href="{{ asset('favicons/favicon.png') }}">
    <style>
        :root {
            --ink: #17202a;
            --muted: #68737f;
            --brand: #149bb1;
            --brand-dark: #08788c;
            --paper: #fff;
            --line: #dfe7ea;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: var(--ink);
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 12% 16%, rgba(20, 155, 177, .18), transparent 28rem),
                radial-gradient(circle at 88% 86%, rgba(255, 193, 7, .16), transparent 24rem),
                #f3f7f8;
        }
        .shell {
            width: min(920px, 100%);
            overflow: hidden;
            display: grid;
            grid-template-columns: 210px 1fr;
            border: 1px solid rgba(223, 231, 234, .9);
            border-radius: 24px;
            background: var(--paper);
            box-shadow: 0 24px 70px rgba(23, 32, 42, .14);
        }
        .mark {
            min-height: 380px;
            display: grid;
            place-items: center;
            position: relative;
            color: #fff;
            background: linear-gradient(145deg, var(--brand-dark), var(--brand));
        }
        .mark::after {
            content: "";
            position: absolute;
            width: 190px;
            height: 190px;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 50%;
            transform: translate(-42%, 60%);
        }
        .code {
            position: relative;
            z-index: 1;
            font-size: 58px;
            font-weight: 800;
            letter-spacing: -.05em;
        }
        .content { padding: 54px 58px; align-self: center; }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 38px;
            color: var(--brand-dark);
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .brand img { width: 36px; height: 36px; object-fit: contain; }
        .eyebrow {
            margin-bottom: 10px;
            color: var(--brand-dark);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        h1 { margin: 0 0 16px; font-size: clamp(30px, 5vw, 48px); line-height: 1.05; letter-spacing: -.035em; }
        p { max-width: 570px; margin: 0; color: var(--muted); font-size: 17px; line-height: 1.65; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 20px;
            border-radius: 10px;
            color: #fff;
            background: var(--brand);
            font-weight: 700;
            text-decoration: none;
            transition: transform .15s ease, background .15s ease;
        }
        .button:hover { color: #fff; background: var(--brand-dark); transform: translateY(-1px); }
        .button.secondary { color: var(--ink); background: #eef3f4; }
        .button.secondary:hover { color: var(--ink); background: #e2eaec; }
        @media (max-width: 680px) {
            .shell { grid-template-columns: 1fr; }
            .mark { min-height: 118px; }
            .mark::after { display: none; }
            .code { font-size: 42px; }
            .content { padding: 34px 26px 38px; }
            .brand { margin-bottom: 26px; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <aside class="mark" aria-hidden="true">
            <div class="code">{{ $status }}</div>
        </aside>
        <section class="content">
            <div class="brand">
                <img src="{{ asset('img/logo.png') }}" alt="">
                <span>Testiapp</span>
            </div>
            <div class="eyebrow">{{ $eyebrow }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            <div class="actions">
                <a class="button" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
                <button class="button secondary" type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">
                    Volver
                </button>
            </div>
        </section>
    </main>
</body>
</html>
