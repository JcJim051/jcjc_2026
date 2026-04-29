<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h2 class="mb-2">Asistencia Reunión de Campaña</h2>
                        <p class="mb-1"><strong>Lugar:</strong> {{ $session->reunion->lugar ?: 'Sin lugar' }}</p>
                        <p class="mb-1"><strong>Fecha:</strong> {{ $session->reunion->fecha ?: 'Sin fecha' }}</p>
                        <p class="mb-3"><strong>Horario:</strong> {{ $session->reunion->hora_inicio ?: '--:--' }} - {{ $session->reunion->hora_fin ?: '--:--' }}</p>
                        <p class="mb-3"><strong>Asistencias registradas:</strong> {{ $asistentesRegistrados }}</p>
                        <p class="text-muted">Escanea este código. Cambia cada 30 segundos.</p>
                        <p class="mb-3"><strong>Siguiente cambio en:</strong> <span id="countdown">30</span> s</p>

                        <img
                            src="https://quickchart.io/qr?size=320&text={{ urlencode($formUrl) }}"
                            alt="QR asistencia dinámica"
                            width="320"
                            height="320"
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
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
</body>
</html>

