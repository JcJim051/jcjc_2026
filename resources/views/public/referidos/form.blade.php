<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referir Testigo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --brand-primary: #0f4c81;
            --brand-accent: #00a86b;
            --brand-bg-1: #f3f8ff;
            --brand-bg-2: #e8f5f0;
        }
        body {
            background: linear-gradient(130deg, var(--brand-bg-1), var(--brand-bg-2));
            min-height: 100vh;
            color: #17314b;
        }
        .brand-wrap {
            max-width: 1140px;
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
        .brand-card .card-header {
            border-bottom: 0;
            background: linear-gradient(120deg, var(--brand-primary), #1f6aa8);
            color: #fff;
            padding: 22px 24px;
        }
        .brand-title {
            margin: 0;
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: .2px;
        }
        .brand-subtitle {
            margin: 10px 0 0;
            font-size: .95rem;
            opacity: .95;
        }
        .brand-card .card-body {
            padding: 24px;
        }
        .btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            border-radius: 10px;
            font-weight: 600;
            padding: 10px 16px;
        }
        .btn-primary:hover {
            background: #0c406c;
            border-color: #0c406c;
        }
        .btn-link {
            font-weight: 600;
            color: var(--brand-primary);
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
    </style>
</head>
<body>
<div class="brand-wrap">
    <div class="card brand-card">
        <div class="card-header">
            <h4 class="brand-title">Formulario de Referidos</h4>
            <p class="brand-subtitle">
                <strong>Departamento:</strong> {{ $departamento ?? 'N/D' }}
                | <strong>Municipio:</strong> {{ $municipio ?? 'N/D' }}
                | <strong>Comuna:</strong> {{ $comuna ?: 'Todas' }}
            </p>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('public.referidos.store', $token->token) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="cedula">Cédula</label>
                        <input type="text" name="cedula" id="cedula" class="form-control" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="nombre">Nombre completo</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="email">Correo</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="telefono">Teléfono</label>
                        <input type="text" name="telefono" id="telefono" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="puesto_id">Puesto de votación</label>
                        <select name="puesto_id" id="puesto_id" class="form-control" required></select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="mesa_num">Mesa</label>
                        <select name="mesa_num" id="mesa_num" class="form-control" required>
                            <option value="">Seleccione</option>
                        </select>
                        <small id="mesa_help" class="form-text text-muted"></small>
                        <small id="cupo_help" class="form-text text-muted"></small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="cedula_pdf">PDF cédula (máx 2MB)</label>
                        <input type="file" name="cedula_pdf" id="cedula_pdf" class="form-control" accept="application/pdf" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Registrar referido</button>
                <a class="btn btn-link" href="{{ route('public.referidos.seguimiento', $token->token) }}">Ver seguimiento</a>
            </form>
        </div>
    </div>
    <div class="brand-footer">Creado por Jonathan Jimenez</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    $('#puesto_id').select2({
        placeholder: 'Buscar puesto...',
        width: '100%',
        minimumInputLength: 0,
        ajax: {
            url: '{{ route('public.referidos.puestos', $token->token) }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term || ''
                };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#puesto_id').on('select2:select', function (e) {
        var data = e.params.data;
        var puestoId = data && data.id ? data.id : null;
        var mesaSelect = $('#mesa_num');
        mesaSelect.empty().append('<option value=\"\">Seleccione</option>');
        if (data && data.mesas_no_asignadas !== undefined) {
            $('#mesa_help').text('Mesas no asignadas: ' + data.mesas_no_asignadas);
        } else if (data && data.mesas_total) {
            $('#mesa_help').text('Mesas disponibles: 1 a ' + data.mesas_total);
        }
        if (data && data.meta_objetivo !== undefined) {
            $('#cupo_help').text(
                'Espacios meta (' + (data.meta_pct || 100) + '%): ' + (data.espacios_40_libres || 0)
                + ' libres de ' + data.meta_objetivo
            );
        } else {
            $('#cupo_help').text('');
        }

        if (!puestoId) {
            return;
        }

        $.ajax({
            url: '{{ route('public.referidos.mesas_disponibles', $token->token) }}',
            dataType: 'json',
            data: {
                puesto_id: puestoId
            }
        }).done(function (resp) {
            var items = (resp && resp.results) ? resp.results : [];
            if (!items.length) {
                $('#mesa_help').text('No hay mesas disponibles para este puesto.');
                if (resp && resp.meta_objetivo !== undefined) {
                    $('#cupo_help').text(
                        'Espacios meta (' + (resp.meta_pct || 100) + '%): ' + (resp.espacios_40_libres || 0)
                        + ' libres de ' + resp.meta_objetivo
                    );
                }
                return;
            }
            items.forEach(function (it) {
                mesaSelect.append('<option value=\"' + it.id + '\">' + it.text + '</option>');
            });
            if (resp) {
                $('#mesa_help').text('Mesas no asignadas: ' + (resp.mesas_no_asignadas || items.length));
                if (resp.meta_objetivo !== undefined) {
                    $('#cupo_help').text(
                        'Espacios meta (' + (resp.meta_pct || 100) + '%): ' + (resp.espacios_40_libres || 0)
                        + ' libres de ' + resp.meta_objetivo
                    );
                }
            }
        }).fail(function () {
            $('#mesa_help').text('No se pudieron cargar las mesas disponibles.');
            $('#cupo_help').text('');
        });
    });

    // Forzar carga inicial sin escribir
    $('#puesto_id').on('select2:open', function(){
        if (!$('#puesto_id').data('loaded-once')) {
            $('#puesto_id').data('loaded-once', true);
            $('.select2-search__field').val(' ').trigger('input');
        }
    });
});
</script>
</body>
</html>
