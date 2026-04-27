<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Referido</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            max-width: 860px;
            margin: 30px auto;
            padding: 0 12px;
        }
        .brand-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 34px rgba(15, 76, 129, 0.16);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.96);
        }
        .brand-header {
            background: linear-gradient(120deg, var(--brand-primary), #1f6aa8);
            color: #fff;
            padding: 20px 24px;
        }
        .brand-header h4 {
            margin: 0;
            font-weight: 700;
        }
        .brand-body {
            padding: 22px 24px;
        }
        .form-control {
            border-radius: 10px;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            border-radius: 10px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
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
    </style>
</head>
<body>
<div class="brand-wrap">
    <div class="brand-card">
        <div class="brand-header">
            <h4>Editar Referido</h4>
        </div>
        <div class="brand-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="mb-3">
                <strong>Puesto actual:</strong> {{ $referido->puesto }} - {{ $referido->municipio }}
            </p>
            <p class="mb-3">
                <strong>PDF actual:</strong>
                <a href="{{ asset('storage/' . $referido->cedula_pdf_path) }}" target="_blank">Ver PDF</a>
            </p>

            <form method="POST" action="{{ route('public.referidos.update', [$token->token, $referido->id]) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="cedula">Cédula</label>
                        <input type="text" name="cedula" id="cedula" class="form-control" value="{{ old('cedula', $referido->cedula) }}" required>
                    </div>
                    <div class="form-group col-md-8">
                        <label for="nombre">Nombre</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $referido->nombre) }}" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="puesto_id">Puesto</label>
                        <select name="puesto_id" id="puesto_id" class="form-control" required>
                            @foreach (($puestos ?? collect()) as $p)
                                <option value="{{ $p->id }}"
                                    data-mesas="{{ $p->mesas_total }}"
                                    {{ (string) old('puesto_id', $referido->eleccion_puesto_id) === (string) $p->id ? 'selected' : '' }}>
                                    {{ $p->puesto }} - {{ $p->municipio }} (mesas: {{ $p->mesas_total }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted" id="mesas_help"></small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label for="email">Correo</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $referido->email) }}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="telefono">Teléfono</label>
                        <input type="text" name="telefono" id="telefono" class="form-control" value="{{ old('telefono', $referido->telefono) }}" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="mesa_num">Mesa</label>
                        <select name="mesa_num" id="mesa_num" class="form-control" required>
                            <option value="">Seleccione mesa</option>
                            @foreach (($mesas_disponibles ?? collect()) as $mesa)
                                <option value="{{ $mesa }}" {{ (string) old('mesa_num', $referido->mesa_num) === (string) $mesa ? 'selected' : '' }}>
                                    Mesa {{ $mesa }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label for="cedula_pdf">Nuevo PDF de cédula (opcional, máx 2MB)</label>
                        <input type="file" name="cedula_pdf" id="cedula_pdf" class="form-control" accept="application/pdf">
                        <small class="text-muted">Si subes uno nuevo, reemplaza el PDF actual.</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a href="{{ route('public.referidos.seguimiento', $token->token) }}" class="btn btn-secondary">Volver</a>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    (function () {
        if (window.jQuery && $('#puesto_id').length) {
            $('#puesto_id').select2({
                width: '100%',
                placeholder: 'Seleccione puesto'
            });
        }

        var mesasUrl = @json(route('public.referidos.mesas_disponibles', $token->token));
        var referidoId = @json($referido->id);

        function setMesaOptions(items, selectedValue) {
            var mesa = document.getElementById('mesa_num');
            if (!mesa) return;
            mesa.innerHTML = '';

            var first = document.createElement('option');
            first.value = '';
            first.textContent = 'Seleccione mesa';
            mesa.appendChild(first);

            (items || []).forEach(function (it) {
                var opt = document.createElement('option');
                opt.value = String(it.id);
                opt.textContent = it.text || ('Mesa ' + it.id);
                if (selectedValue && String(selectedValue) === String(it.id)) {
                    opt.selected = true;
                }
                mesa.appendChild(opt);
            });
        }

        async function refreshMesas(selectedMesa) {
            var puesto = document.getElementById('puesto_id');
            var help = document.getElementById('mesas_help');
            if (!puesto) return;

            var puestoId = puesto.value;
            if (!puestoId) {
                setMesaOptions([], null);
                if (help) help.textContent = '';
                return;
            }

            try {
                var url = mesasUrl + '?puesto_id=' + encodeURIComponent(puestoId) + '&referido_id=' + encodeURIComponent(referidoId);
                var res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                var data = await res.json();
                setMesaOptions(data.results || [], selectedMesa || null);
                if (help) {
                    var max = parseInt(data.max || '0', 10);
                    help.textContent = max > 0
                        ? ('Mesas disponibles para este puesto: 1 a ' + max)
                        : 'No hay mesas disponibles para este puesto.';
                }
            } catch (e) {
                setMesaOptions([], null);
                if (help) help.textContent = 'No se pudieron cargar las mesas disponibles.';
            }
        }

        function refreshMesaLimit() {
            var puesto = document.getElementById('puesto_id');
            var help = document.getElementById('mesas_help');
            if (!puesto || !help) return;

            var selected = puesto.options[puesto.selectedIndex];
            var total = parseInt(selected.getAttribute('data-mesas') || '0', 10);
            if (!isNaN(total) && total > 0) help.textContent = 'Mesas disponibles para este puesto: 1 a ' + total;
        }

        document.getElementById('puesto_id')?.addEventListener('change', function () {
            refreshMesaLimit();
            refreshMesas(null);
        });
        refreshMesaLimit();
        refreshMesas(@json(old('mesa_num', $referido->mesa_num)));
    })();
</script>
</body>
</html>
