@extends('public.abogados.layout')

@section('title', 'Asistencia Comité Electoral')
@section('heading', 'Asistencia Comité Electoral')
@section('subtitle')
    Consulta tu caracterización con la cédula, confirma tus datos y registra la asistencia.
@stop

@section('css')
<style>
    .attendance-event {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: center;
        padding: 16px 18px;
        margin-bottom: 24px;
        border: 1px solid #dfe8ed;
        border-radius: 14px;
        background: #f7fafb;
    }
    .attendance-event strong { color: #143a5a; }
    .lookup-box, .contact-card {
        border: 1px solid #dce6eb;
        border-radius: 16px;
        padding: 22px;
        background: #fff;
    }
    .lookup-actions { display: flex; gap: 10px; align-items: flex-end; }
    .lookup-actions .form-group { flex: 1; margin-bottom: 0; }
    .contact-card {
        display: none;
        margin-top: 20px;
        border-top: 4px solid #2672a8;
        box-shadow: 0 12px 30px rgba(20, 58, 90, .08);
    }
    .contact-card.is-visible { display: block; }
    .person-name {
        padding: 14px 16px;
        margin-bottom: 18px;
        border-radius: 12px;
        background: #eaf3f8;
        color: #143a5a;
        font-size: 1.08rem;
        font-weight: 800;
    }
    .update-note {
        padding: 12px 14px;
        margin: 5px 0 18px;
        border-left: 4px solid #2c9b67;
        border-radius: 8px;
        background: #edf8f2;
        color: #285b44;
    }
    .lookup-message { display: none; margin-top: 14px; }
    .lookup-message.is-visible { display: block; }
    .success-state { padding: 28px 20px; text-align: center; }
    .success-mark {
        display: inline-flex;
        width: 68px;
        height: 68px;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        border-radius: 50%;
        background: #2c9b67;
        color: #fff;
        font-size: 2rem;
        font-weight: 900;
    }
    .btn-loading { pointer-events: none; opacity: .72; }
    @media (max-width: 575.98px) {
        .attendance-event, .lookup-actions { display: block; }
        .attendance-event .text-muted { margin-top: 6px; }
        .lookup-actions .btn { width: 100%; margin-top: 12px; }
        .lookup-box, .contact-card { padding: 17px; }
    }
</style>
@stop

@section('content')
    <div class="attendance-event">
        <div>
            <strong>{{ $session->reunion->lugar ?: 'Lugar pendiente' }}</strong>
            <div class="text-muted">Reunión #{{ $session->reunion->id }}</div>
        </div>
        <div class="text-muted">
            {{ $session->reunion->fecha ?: 'Fecha pendiente' }}
            · {{ $session->reunion->hora_inicio ?: '--:--' }}–{{ $session->reunion->hora_fin ?: '--:--' }}
        </div>
    </div>

    @if (session('attendance_registered'))
        <div class="success-state">
            <div class="success-mark">✓</div>
            <h2 class="h4 font-weight-bold">Asistencia confirmada</h2>
            <p class="text-muted mb-0">{{ session('info') }}</p>
        </div>
    @else
        <form id="attendanceForm" method="post" action="{{ route('asistencia.reunion.submit', $publicToken) }}">
            @csrf
            <input type="hidden" name="slot" value="{{ $slot }}">
            <input type="hidden" name="token" value="{{ $hashToken }}">
            <input type="hidden" id="nombre" name="nombre" value="{{ old('nombre') }}">

            <div class="section-title">1. Consulta tu información</div>
            <div class="lookup-box">
                <p class="text-muted">Ingresa únicamente tu número de cédula. Te mostraremos los datos registrados para que puedas confirmarlos o corregirlos.</p>
                <div class="lookup-actions">
                    <div class="form-group">
                        <label for="cc">Número de cédula</label>
                        <input class="form-control form-control-lg" type="text" id="cc" name="cc" value="{{ old('cc') }}" inputmode="numeric" autocomplete="off" required>
                    </div>
                    <button type="button" id="lookupButton" class="btn btn-primary btn-lg">Consultar mis datos</button>
                </div>
                <div id="lookupMessage" class="lookup-message alert mb-0" role="alert"></div>
            </div>

            <div id="contactCard" class="contact-card {{ old('correo') || old('telefono') ? 'is-visible' : '' }}">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <div class="section-title border-0 mb-0 p-0">2. Confirma tus datos</div>
                    <button type="button" id="changeCedula" class="btn btn-link px-0">Cambiar cédula</button>
                </div>

                <div id="personName" class="person-name">{{ old('nombre') ?: 'Datos encontrados' }}</div>
                <div class="update-note">
                    Revisa el correo y el celular. Si haces una corrección, también actualizaremos tu caracterización.
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="correo">Correo electrónico</label>
                        <input class="form-control form-control-lg" type="email" id="correo" name="correo" value="{{ old('correo') }}" autocomplete="email" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="telefono">Celular</label>
                        <input class="form-control form-control-lg" type="text" id="telefono" name="telefono" value="{{ old('telefono') }}" inputmode="tel" autocomplete="tel" required>
                    </div>
                </div>

                <button type="submit" id="submitButton" class="btn btn-success btn-lg btn-block mt-2">
                    Confirmar datos y registrar asistencia
                </button>
            </div>
        </form>
    @endif
@stop

@if (!session('attendance_registered'))
@section('js')
<script>
(function () {
    var lookupUrl = @json(route('asistencia.reunion.lookup', $publicToken));
    var csrfToken = @json(csrf_token());
    var slot = @json((string) $slot);
    var formToken = @json($hashToken);
    var lookupButton = document.getElementById('lookupButton');
    var changeButton = document.getElementById('changeCedula');
    var contactCard = document.getElementById('contactCard');
    var message = document.getElementById('lookupMessage');
    var cedula = document.getElementById('cc');
    var nombre = document.getElementById('nombre');
    var personName = document.getElementById('personName');
    var correo = document.getElementById('correo');
    var telefono = document.getElementById('telefono');

    function showMessage(text, type) {
        message.className = 'lookup-message is-visible alert alert-' + type + ' mb-0';
        message.textContent = text;
    }

    function clearMessage() {
        message.className = 'lookup-message alert mb-0';
        message.textContent = '';
    }

    function resetContact() {
        contactCard.classList.remove('is-visible');
        nombre.value = '';
        personName.textContent = '';
        correo.value = '';
        telefono.value = '';
    }

    lookupButton.addEventListener('click', function () {
        clearMessage();
        resetContact();

        if (!cedula.value.trim()) {
            showMessage('Ingresa tu número de cédula para continuar.', 'warning');
            cedula.focus();
            return;
        }

        lookupButton.classList.add('btn-loading');
        lookupButton.disabled = true;
        lookupButton.textContent = 'Consultando...';

        fetch(lookupUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                cc: cedula.value,
                slot: slot,
                token: formToken
            })
        })
        .then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.message || 'No fue posible consultar la información. Intenta nuevamente.');
                }
                return payload;
            });
        })
        .then(function (data) {
            nombre.value = data.nombre || '';
            personName.textContent = data.nombre || 'Persona caracterizada';
            correo.value = data.correo || '';
            telefono.value = data.telefono || '';
            contactCard.classList.add('is-visible');
            clearMessage();
            correo.focus();
        })
        .catch(function (error) {
            showMessage(error.message, 'danger');
        })
        .finally(function () {
            lookupButton.classList.remove('btn-loading');
            lookupButton.disabled = false;
            lookupButton.textContent = 'Consultar mis datos';
        });
    });

    changeButton.addEventListener('click', function () {
        resetContact();
        clearMessage();
        cedula.focus();
    });

    cedula.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !contactCard.classList.contains('is-visible')) {
            event.preventDefault();
            lookupButton.click();
        }
    });
})();
</script>
@stop
@endif
