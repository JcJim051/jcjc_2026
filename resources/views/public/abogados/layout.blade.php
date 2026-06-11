<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Equipo electoral')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        :root { --navy: #143a5a; --blue: #2672a8; --green: #2c9b67; --paper: #f3f6f8; }
        body { background: linear-gradient(145deg, #e8f1f5, var(--paper) 45%, #eef5ee); color: #243746; min-height: 100vh; }
        .public-shell { max-width: 1050px; margin: 36px auto; padding: 0 16px 40px; }
        .brand-panel { background: var(--navy); color: #fff; border-radius: 16px 16px 0 0; padding: 25px 30px; }
        .brand-panel h1 { font-size: 1.65rem; margin: 0; font-weight: 700; }
        .content-panel { background: #fff; border-radius: 0 0 16px 16px; box-shadow: 0 16px 45px rgba(20,58,90,.14); padding: 30px; }
        .section-title { color: var(--navy); font-size: 1rem; font-weight: 700; border-bottom: 2px solid #e7eef2; padding-bottom: 9px; margin: 8px 0 18px; }
        .btn-primary { background: var(--blue); border-color: var(--blue); }
        .btn-success { background: var(--green); border-color: var(--green); }
        .expires { display: inline-block; background: rgba(255,255,255,.14); border-radius: 20px; padding: 5px 11px; font-size: .83rem; margin-top: 10px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/testiapp-tokens.css') }}">
    @yield('css')
</head>
<body>
    <main class="public-shell">
        <header class="brand-panel">
            <div class="token-brand" style="color:#fff;">
                <img src="{{ asset('img/logo.png') }}" alt="Testiapp">
                <span>Testiapp</span>
            </div>
            <h1>@yield('heading')</h1>
            @hasSection('subtitle')<div class="mt-2">@yield('subtitle')</div>@endif
            @isset($accessToken)
                <span class="expires">Disponible hasta {{ $accessToken->expires_at->format('Y-m-d H:i') }}</span>
            @endisset
        </header>
        <section class="content-panel">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </section>
    </main>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    @yield('js')
</body>
</html>
