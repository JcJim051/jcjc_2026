<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Un fallo de permisos en el log no debe impedir que se muestre la vista amable.
     */
    public function report(Throwable $exception)
    {
        try {
            parent::report($exception);
        } catch (Throwable $loggingException) {
            error_log('Testiapp no pudo escribir el error en el log: ' . $loggingException->getMessage());
        }
    }

    /**
     * Renderiza errores web sin exponer detalles técnicos de Laravel.
     */
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson()) {
            return parent::render($request, $exception);
        }

        // Estas excepciones conservan el flujo normal de redirección y validación.
        if ($exception instanceof ValidationException || $exception instanceof AuthenticationException) {
            return parent::render($request, $exception);
        }

        if ($exception instanceof InvalidSignatureException) {
            return response()->view('errors.app', [
                'status' => 410,
                'eyebrow' => 'Enlace vencido',
                'title' => 'Este enlace ya caducó',
                'message' => 'Por seguridad, el enlace tenía una vigencia limitada. Escanea nuevamente el código QR o solicita un enlace actualizado.',
                'actionUrl' => url('/'),
                'actionLabel' => 'Ir al inicio',
            ], 410);
        }

        if ($exception instanceof TokenMismatchException) {
            return response()->view('errors.app', [
                'status' => 419,
                'eyebrow' => 'Sesión vencida',
                'title' => 'Tu sesión necesita renovarse',
                'message' => 'La página permaneció abierta durante un tiempo. Actualízala e intenta nuevamente; la información diligenciada no fue enviada.',
                'actionUrl' => $request->fullUrl(),
                'actionLabel' => 'Actualizar página',
            ], 419);
        }

        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        if ($exception instanceof ThrottleRequestsException) {
            $status = 429;
        }

        $content = $this->friendlyErrorContent($status);

        return response()->view('errors.app', array_merge($content, [
            'status' => $status,
            'actionUrl' => $status === 401 ? route('login') : url('/'),
            'actionLabel' => $status === 401 ? 'Iniciar sesión' : 'Ir al inicio',
        ]), $status);
    }

    private function friendlyErrorContent(int $status): array
    {
        $messages = [
            400 => ['eyebrow' => 'Solicitud no válida', 'title' => 'No pudimos procesar la solicitud', 'message' => 'Revisa la información enviada e intenta nuevamente.'],
            401 => ['eyebrow' => 'Acceso requerido', 'title' => 'Debes iniciar sesión', 'message' => 'Tu sesión no está activa o ya finalizó. Ingresa nuevamente para continuar.'],
            403 => ['eyebrow' => 'Acceso restringido', 'title' => 'No tienes permiso para ingresar', 'message' => 'Tu usuario no cuenta con autorización para consultar esta sección.'],
            404 => ['eyebrow' => 'Página no encontrada', 'title' => 'No encontramos lo que buscas', 'message' => 'El enlace pudo cambiar, haber sido eliminado o estar escrito incorrectamente.'],
            410 => ['eyebrow' => 'Contenido no disponible', 'title' => 'Este recurso ya no está disponible', 'message' => 'Solicita un enlace actualizado para continuar.'],
            419 => ['eyebrow' => 'Sesión vencida', 'title' => 'La página necesita actualizarse', 'message' => 'Actualiza la página e intenta realizar nuevamente la operación.'],
            429 => ['eyebrow' => 'Espera un momento', 'title' => 'Se realizaron demasiados intentos', 'message' => 'Espera unos minutos antes de volver a intentarlo.'],
            503 => ['eyebrow' => 'Mantenimiento', 'title' => 'Volveremos pronto', 'message' => 'Testiapp está recibiendo una actualización. Intenta ingresar nuevamente en unos minutos.'],
        ];

        return $messages[$status] ?? [
            'eyebrow' => 'No pudimos completar la operación',
            'title' => 'Ocurrió un inconveniente',
            'message' => 'El equipo técnico recibió el reporte. Intenta nuevamente en unos minutos.',
        ];
    }
}
