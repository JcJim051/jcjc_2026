<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="mb-3">Registro de Asistencia</h3>
                        <p class="text-muted mb-4">Reunión #{{ $session->reunion->id }} - {{ $session->reunion->lugar ?: 'Sin lugar' }}</p>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        @if (session('info'))
                            <div class="alert alert-success">
                                {{ session('info') }}
                            </div>
                        @endif

                        <form method="post" action="{{ route('asistencia.reunion.submit', $publicToken) }}">
                            @csrf
                            <input type="hidden" name="slot" value="{{ $slot }}">
                            <input type="hidden" name="token" value="{{ $hashToken }}">

                            <div class="mb-3">
                                <label class="form-label" for="cc">Cédula</label>
                                <input class="form-control" type="text" id="cc" name="cc" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="correo">Correo registrado</label>
                                <input class="form-control" type="email" id="correo" name="correo" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Registrar asistencia</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

