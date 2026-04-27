<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario Cerrado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --brand-primary: #0f4c81;
            --brand-bg-1: #f3f8ff;
            --brand-bg-2: #e8f5f0;
        }
        body {
            background: linear-gradient(130deg, var(--brand-bg-1), var(--brand-bg-2));
            min-height: 100vh;
            color: #17314b;
        }
        .brand-wrap {
            max-width: 760px;
            margin: 46px auto;
            padding: 0 12px;
        }
        .brand-alert {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 34px rgba(15, 76, 129, 0.16);
            background: rgba(255, 255, 255, 0.96);
            margin-bottom: 0;
        }
        .btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #0c406c;
            border-color: #0c406c;
        }
        .brand-footer {
            margin-top: 16px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            text-align: center;
            color: var(--brand-primary);
            font-weight: 600;
            font-size: .9rem;
            padding: 10px 12px;
            box-shadow: 0 8px 20px rgba(15, 76, 129, 0.08);
        }
    </style>
</head>
<body>
<div class="brand-wrap">
    <div class="alert alert-warning brand-alert">
        <h4>Formulario cerrado</h4>
        <p>Este enlace ya no recibe nuevos referidos. El seguimiento sigue disponible.</p>
        <a class="btn btn-primary" href="{{ route('public.referidos.seguimiento', $token->token) }}">Ir a seguimiento</a>
    </div>
    <div class="brand-footer">Creado por Jonathan Jimenez</div>
</div>
</body>
</html>
