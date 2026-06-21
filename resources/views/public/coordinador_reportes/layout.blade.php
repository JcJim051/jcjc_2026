<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Reporte Operativo')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/testiapp-tokens.css') }}">
    <style>
        :root { --brand-primary:#0f4c81; --brand-bg-1:#eef5ff; --brand-bg-2:#e8f5f0; }
        body { background: linear-gradient(135deg, var(--brand-bg-1), var(--brand-bg-2)); min-height: 100vh; color:#17314b; font-size:.92rem; }
        .brand-wrap { max-width: 1180px; margin: 22px auto; padding: 0 12px; }
        .brand-card { border:0; border-radius:16px; box-shadow:0 14px 30px rgba(15,76,129,.12); overflow:hidden; background:rgba(255,255,255,.97); }
        .brand-header { background: linear-gradient(120deg, var(--brand-primary), #1f6aa8); color:#fff; padding:18px 22px; }
        .brand-top { display:flex; align-items:center; gap:12px; }
        .brand-logo-wrap { width:40px; height:40px; border-radius:10px; display:grid; place-items:center; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.24); }
        .brand-logo-wrap img { width:26px; height:26px; object-fit:contain; }
        .brand-title { margin:0; font-weight:800; font-size:1.45rem; line-height:1.15; }
        .brand-subtitle { margin:6px 0 0; opacity:.95; font-size:.9rem; line-height:1.35; }
        .brand-body { padding:18px 20px 22px; }
        .soft-card { border:1px solid #dde8f4; border-radius:14px; box-shadow:0 6px 16px rgba(15,76,129,.05); }
        .soft-card .card-header { background:#f7fbff; border-bottom:1px solid #e2ebf4; font-weight:700; font-size:.95rem; padding:.75rem 1rem; }
        .soft-card .card-body { padding:1rem; }
        .btn, .btn-primary,.btn-outline-primary { border-radius:9px; font-weight:700; font-size:.9rem; }
        .metric-box { border-radius:14px; background:#fff; border:1px solid #e2ebf4; padding:16px; height:100%; box-shadow:0 6px 14px rgba(15,76,129,.05); }
        .metric-box .label { font-size:.75rem; font-weight:700; color:#4a637d; text-transform:uppercase; }
        .metric-box .value { font-size:1.45rem; font-weight:800; margin-top:4px; }
        .badge-soft { padding:.4rem .65rem; border-radius:999px; font-weight:700; font-size:.78rem; }
        .badge-ok { background:#d9f3e8; color:#0c6b48; }
        .badge-pending { background:#ffe9c7; color:#8a5600; }
        .badge-empty { background:#e9eef4; color:#52616f; }
        .brand-footer { margin-top:14px; background:rgba(255,255,255,.92); border-radius:12px; text-align:center; color:var(--brand-primary); font-weight:600; font-size:.85rem; padding:9px 12px; box-shadow:0 8px 20px rgba(15,76,129,.08); }
        .alert { font-size:.9rem; }
        .form-control, .custom-select { font-size:.9rem; min-height:40px; }
        textarea.form-control { min-height:92px; }
        label { font-size:.83rem; font-weight:700; margin-bottom:.35rem; }
        .table th, .table td { font-size:.84rem; vertical-align:middle; }
        .table thead th { font-size:.78rem; }
        .form-text, small { font-size:.78rem; }
        @media (max-width: 768px) {
            body { font-size:.9rem; }
            .brand-wrap { margin: 12px auto; }
            .brand-header { padding:16px 16px 14px; }
            .brand-body { padding:16px; }
            .brand-title { font-size:1.15rem; }
            .brand-subtitle { font-size:.82rem; }
            .soft-card .card-header { font-size:.9rem; }
            .table th, .table td { font-size:.8rem; }
        }
    </style>
</head>
<body>
<div class="brand-wrap">
    <div class="brand-card">
        <div class="brand-header">
            <div class="brand-top">
                <div class="brand-logo-wrap"><img src="{{ asset('img/logo.png') }}" alt="Testiapp"></div>
                <div>
                    <h3 class="brand-title">@yield('brand_title', 'Reporte Operativo Testiapp')</h3>
                    <p class="brand-subtitle mb-0">@yield('brand_subtitle')</p>
                </div>
            </div>
        </div>
        <div class="brand-body">
            @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content_body')
        </div>
    </div>
    <div class="brand-footer">Creado por <a href="https://procesos.shipper.com.co" target="_blank" rel="noopener noreferrer">Jonathan Jimenez</a></div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
