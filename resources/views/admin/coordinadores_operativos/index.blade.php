@extends('adminlte::page')
@section('title', 'Coordinadores')

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
@endsection

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h1 class="mb-1">Coordinadores por DIVIPOL</h1>
            <small class="text-muted">Visualiza cupos remanentes, coordinadores activos y gestiona la carga masiva desde un módulo propio.</small>
        </div>
        <div class="d-flex flex-wrap" style="gap:8px;">
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalCrearCoordinador">
                <i class="fas fa-user-plus mr-1"></i> Crear coordinador
            </button>
            <form method="POST" action="{{ route('admin.coordinadores_operativos.integrate') }}" onsubmit="return confirm('Esto va a reclasificar coordinadores existentes de esta elección entre Rem, Mesa y Sin acreditar. ¿Continuamos?');">
                @csrf
                <input type="hidden" name="eleccion_id" value="{{ $eleccionId }}">
                <button type="submit" class="btn btn-warning btn-sm">
                    <i class="fas fa-bolt mr-1"></i> Integración rápida
                </button>
            </form>
            <a href="{{ route('admin.coordinadores_operativos.export', ['eleccion_id' => $eleccionId]) }}" class="btn btn-success btn-sm">
                <i class="fas fa-file-excel mr-1"></i> Descargar tabla
            </a>
            <a href="{{ route('admin.abogados.import_coordinadores.template') }}" class="btn btn-outline-info btn-sm">
                <i class="fas fa-download mr-1"></i> Plantilla
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('coordinadores_import_errors'))
        <div class="alert alert-warning">
            <strong>Filas con observaciones:</strong>
            <ul class="mb-0 pl-3 mt-2">
                @foreach(session('coordinadores_import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-6 form-group mb-2">
                    <form method="GET">
                        <label>Elección</label>
                        <select name="eleccion_id" class="form-control" onchange="this.form.submit()">
                            @foreach($elecciones as $item)
                                <option value="{{ $item->id }}" {{ (int) $eleccionId === (int) $item->id ? 'selected' : '' }}>
                                    #{{ $item->id }} - {{ $item->nombre }} {{ $item->tipo ? '(' . $item->tipo . ')' : '' }} [{{ $item->estado }}]
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="col-md-6 form-group mb-2 text-md-right">
                    <label class="d-block">Acciones rápidas</label>
                    <form method="POST" action="{{ route('admin.abogados.reubicar_coordinadores.preview') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="eleccion_id" value="{{ $eleccionId }}">
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <i class="fas fa-random mr-1"></i> Ajuste transitorio
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-3"><div class="small-box bg-info"><div class="inner"><h3>{{ $summary['puestos'] }}</h3><p>Puestos</p></div><div class="icon"><i class="fas fa-school"></i></div></div></div>
        <div class="col-md-3 mb-3"><div class="small-box bg-primary"><div class="inner"><h3>{{ $summary['coordinadores'] }}</h3><p>Coordinadores activos</p></div><div class="icon"><i class="fas fa-users"></i></div></div></div>
        <div class="col-md-3 mb-3"><div class="small-box bg-success"><div class="inner"><h3>{{ $summary['validados'] }}</h3><p>Validados</p></div><div class="icon"><i class="fas fa-check"></i></div></div></div>
        <div class="col-md-3 mb-3"><div class="small-box bg-warning"><div class="inner"><h3>{{ $summary['disponibles'] }}</h3><p>Cupos rem disponibles</p><small>Rem: {{ $summary['rem'] }} | Mesa: {{ $summary['mesa'] }} | Sin acreditar: {{ $summary['sin_acreditar'] }}</small></div><div class="icon"><i class="fas fa-layer-group"></i></div></div></div>
    </div>

    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title"><strong>Importar coordinadores por elección</strong></h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.abogados.import_coordinadores') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Elección activa</label>
                        <select name="eleccion_id" class="form-control" required>
                            @foreach($elecciones as $item)
                                <option value="{{ $item->id }}" {{ (string) old('eleccion_id', $eleccionId) === (string) $item->id ? 'selected' : '' }}>
                                    #{{ $item->id }} - {{ $item->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Excel / CSV</label>
                        <input type="file" name="archivo" class="form-control-file" accept=".xlsx,.xls,.csv,.txt">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>URL Google Sheet (opcional)</label>
                        <input type="url" name="spreadsheet_url" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>GID</label>
                        <input type="text" name="gid" class="form-control" placeholder="0">
                    </div>
                    <div class="col-md-1 form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-info w-100">Cargar</button>
                    </div>
                </div>
                <small class="text-muted">Columnas esperadas: <strong>cedula, nombre, puesto, correo, telefono, direccion, observacion</strong>. La carga nueva decide automáticamente si entra como <strong>Rem</strong>, <strong>Mesa</strong> o <strong>Sin acreditar</strong>.</small>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><strong>DIVIPOL coordinadores</strong></h3>
        </div>
        <div class="card-body table-responsive">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="filtroMunicipio" class="mb-1">Filtrar por municipio</label>
                    <select id="filtroMunicipio" class="form-control form-control-sm">
                        <option value="">Todos</option>
                    </select>
                </div>
            </div>
            <table id="coordinadoresTable" class="table table-hover table-sm mb-0 nowrap" style="width:100%;">
                <thead>
                    <tr>
                        <th>Municipio</th>
                        <th>Comuna</th>
                        <th>Puesto</th>
                        <th>Mesas</th>
                        <th>Remanentes</th>
                        <th>Coordinadores</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td data-order="{{ str_pad((string) $row['municipio_codigo'], 3, '0', STR_PAD_LEFT) }}">{{ $row['municipio'] }}</td>
                            <td>{{ $row['comuna'] }}</td>
                            <td>{{ $row['puesto'] }}</td>
                            <td>{{ $row['mesas_total'] }}</td>
                            <td>
                                <span class="badge badge-light">Libres {{ $row['rem_disponibles'] }}</span>
                                <span class="badge badge-secondary">Total {{ $row['rem_permitidos'] }}</span>
                            </td>
                            <td data-search="{{ collect($row['coordinadores'])->map(function ($coord) use ($row) { return trim(($coord['nombre'] ?? '') . ' ' . ($coord['cc'] ?? '') . ' ' . ($coord['correo'] ?? '') . ' ' . ($coord['telefono'] ?? '') . ' ' . ($coord['tipo_label'] ?? '') . ' ' . ($row['puesto'] ?? '') . ' ' . ($row['municipio'] ?? '') . ' ' . ($row['comuna'] ?? '')); })->implode(' | ') }}">
                                @if(count($row['coordinadores']))
                                    <div class="d-flex flex-column" style="gap:8px; min-width:420px;">
                                        @foreach($row['coordinadores'] as $coord)
                                            <div class="border rounded p-2 bg-light">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
                                                    <div>
                                                        <div><strong>{{ $coord['nombre'] }}</strong> - {{ $coord['cc'] }}</div>
                                                        <div class="small text-muted">
                                                            {{ $coord['tipo_label'] }}
                                                            @if($coord['correo'])
                                                                | {{ $coord['correo'] }}
                                                            @endif
                                                            @if($coord['telefono'])
                                                                | {{ $coord['telefono'] }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="d-flex flex-wrap" style="gap:6px;">
                                                        <button
                                                            type="button"
                                                            class="btn btn-xs btn-primary js-editar-coordinador"
                                                            data-coord='@json($coord)'
                                                        >
                                                            Editar
                                                        </button>
                                                        <form method="POST" action="{{ $coord['liberar_url'] }}" onsubmit="return confirm('¿Liberar este coordinador del puesto?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-outline-danger">Liberar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">Sin coordinadores</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No hay puestos cargados para esta elección.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalCrearCoordinador" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.coordinadores_operativos.store_manual') }}">
                    @csrf
                    <input type="hidden" name="eleccion_id" value="{{ old('eleccion_id', $eleccionId) }}">
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title">Crear coordinador y asignar puesto</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Nombre</label>
                                <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Cédula</label>
                                <input type="text" name="cc" class="form-control" value="{{ old('cc') }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Correo</label>
                                <input type="email" name="correo" class="form-control" value="{{ old('correo') }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Teléfono</label>
                                <input type="text" name="telefono" class="form-control" value="{{ old('telefono') }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Puesto</label>
                                <select name="codpuesto" class="form-control js-coord-puesto-select" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($puestoOptions as $option)
                                        <option value="{{ $option->codpuesto }}" {{ old('codpuesto') === $option->codpuesto ? 'selected' : '' }}>
                                            {{ $option->label }} | Rem {{ $option->disponibles }} | Mesas {{ $option->mesa_libre }} | Sugiere {{ $option->tipo_sugerido }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Tipo</label>
                                <select name="tipo_asignacion" id="tipoAsignacion" class="form-control" required>
                                    <option value="remanente" {{ old('tipo_asignacion') === 'remanente' ? 'selected' : '' }}>Rem</option>
                                    <option value="mesa" {{ old('tipo_asignacion') === 'mesa' ? 'selected' : '' }}>Mesa</option>
                                    <option value="sin_acreditar" {{ old('tipo_asignacion') === 'sin_acreditar' ? 'selected' : '' }}>Sin acreditar</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="mesaDisponibleWrap" style="display:none;">
                                <label>Mesa disponible</label>
                                <select name="mesa_num" id="mesaDisponible" class="form-control">
                                    <option value="">Seleccione una mesa...</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group d-flex align-items-end">
                                <div id="tipoAyuda" class="small text-muted"></div>
                            </div>
                            <div class="col-12 form-group">
                                <label>Observación</label>
                                <textarea name="observacion" class="form-control" rows="3">{{ old('observacion') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar coordinador</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditarCoordinador" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" id="formEditarCoordinador">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="eleccion_id" value="{{ $eleccionId }}">
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title">Editar coordinador</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Nombre</label>
                                <input type="text" name="nombre" id="editNombre" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Cédula</label>
                                <input type="text" name="cc" id="editCc" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Correo</label>
                                <input type="email" name="correo" id="editCorreo" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Teléfono</label>
                                <input type="text" name="telefono" id="editTelefono" class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Puesto</label>
                                <select name="codpuesto" id="editCodpuesto" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    @foreach($puestoOptions as $option)
                                        <option value="{{ $option->codpuesto }}">
                                            {{ $option->label }} | Rem {{ $option->disponibles }} | Mesas {{ $option->mesa_libre }} | Sugiere {{ $option->tipo_sugerido }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Tipo</label>
                                <select name="tipo_asignacion" id="editTipoAsignacion" class="form-control" required>
                                    <option value="remanente">Rem</option>
                                    <option value="mesa">Mesa</option>
                                    <option value="sin_acreditar">Sin acreditar</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="editMesaDisponibleWrap" style="display:none;">
                                <label>Mesa disponible</label>
                                <select name="mesa_num" id="editMesaDisponible" class="form-control">
                                    <option value="">Seleccione una mesa...</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group d-flex align-items-end">
                                <div id="editTipoAyuda" class="small text-muted"></div>
                            </div>
                            <div class="col-12 form-group">
                                <label>Observación</label>
                                <textarea name="observacion" id="editObservacion" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            const puestosMeta = @json($puestoOptions->keyBy('codpuesto')->map(function ($option) {
                return [
                    'rem_disponibles' => (int) $option->disponibles,
                    'mesas_disponibles' => $option->mesas_disponibles,
                ];
            }));
            const updateBaseUrl = @json(rtrim(route('admin.coordinadores_operativos.index'), '/'));

            $('#coordinadoresTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'asc'], [1, 'asc'], [2, 'asc']],
                scrollX: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
                },
                initComplete: function () {
                    const api = this.api();
                    const municipioColumn = api.column(0);
                    const $filtro = $('#filtroMunicipio');

                    municipioColumn.data().unique().sort().each(function (value) {
                        const text = $('<div>').html(value).text().trim();
                        if (text) {
                            $filtro.append('<option value="' + text.replace(/"/g, '&quot;') + '">' + text + '</option>');
                        }
                    });

                    $filtro.on('change', function () {
                        const value = $.fn.dataTable.util.escapeRegex($(this).val());
                        municipioColumn.search(value ? '^' + value + '$' : '', true, false).draw();
                    });
                }
            });

            $('.js-coord-puesto-select').select2({
                width: '100%',
                placeholder: 'Buscar puesto...',
                dropdownParent: $('#modalCrearCoordinador')
            });

            function refreshAssignmentUi(config) {
                const meta = puestosMeta[$(config.codpuesto).val()] || { rem_disponibles: 0, mesas_disponibles: [] };
                const tipo = $(config.tipo).val();
                const $mesaWrap = $(config.mesaWrap);
                const $mesaSelect = $(config.mesaSelect);
                const $ayuda = $(config.ayuda);
                const selectedMesa = config.selectedMesa();

                $mesaSelect.empty().append('<option value="">Seleccione una mesa...</option>');

                if (tipo === 'remanente') {
                    $mesaWrap.hide();
                    $mesaSelect.prop('required', false);
                    $ayuda.text(meta.rem_disponibles > 0
                        ? 'Espacios rem disponibles: ' + meta.rem_disponibles
                        : 'No hay espacios rem disponibles en este puesto.');
                    return;
                }

                if (tipo === 'mesa') {
                    $mesaWrap.show();
                    $mesaSelect.prop('required', true);
                    (meta.mesas_disponibles || []).forEach(function (mesa) {
                        const selected = String(selectedMesa) === String(mesa) ? ' selected' : '';
                        $mesaSelect.append('<option value="' + mesa + '"' + selected + '>Mesa ' + mesa + '</option>');
                    });
                    $ayuda.text((meta.mesas_disponibles || []).length
                        ? 'Mesas libres en este puesto: ' + meta.mesas_disponibles.join(', ')
                        : 'No hay mesas libres en este puesto.');
                    return;
                }

                $mesaWrap.hide();
                $mesaSelect.prop('required', false);
                $ayuda.text('Se asociará al puesto sin usar rem ni mesa.');
            }

            $('select[name="codpuesto"], #tipoAsignacion').on('change', function () {
                refreshAssignmentUi({
                    codpuesto: 'select[name="codpuesto"]',
                    tipo: '#tipoAsignacion',
                    mesaWrap: '#mesaDisponibleWrap',
                    mesaSelect: '#mesaDisponible',
                    ayuda: '#tipoAyuda',
                    selectedMesa: () => $('#mesaDisponible').val() || @json(old('mesa_num')),
                });
            });

            refreshAssignmentUi({
                codpuesto: 'select[name="codpuesto"]',
                tipo: '#tipoAsignacion',
                mesaWrap: '#mesaDisponibleWrap',
                mesaSelect: '#mesaDisponible',
                ayuda: '#tipoAyuda',
                selectedMesa: () => $('#mesaDisponible').val() || @json(old('mesa_num')),
            });

            $('#editCodpuesto, #editTipoAsignacion').on('change', function () {
                refreshAssignmentUi({
                    codpuesto: '#editCodpuesto',
                    tipo: '#editTipoAsignacion',
                    mesaWrap: '#editMesaDisponibleWrap',
                    mesaSelect: '#editMesaDisponible',
                    ayuda: '#editTipoAyuda',
                    selectedMesa: () => $('#editMesaDisponible').val() || $('#editMesaDisponible').data('selected') || '',
                });
            });

            $(document).on('click', '.js-editar-coordinador', function () {
                const coord = $(this).data('coord');
                const tipo = coord.tipo || 'remanente';

                $('#formEditarCoordinador').attr('action', updateBaseUrl + '/' + coord.coordinacion_id);
                $('#editNombre').val(coord.nombre || '');
                $('#editCc').val(coord.cc || '');
                $('#editCorreo').val(coord.correo || '');
                $('#editTelefono').val(coord.telefono || '');
                $('#editCodpuesto').val(coord.codpuesto || '');
                $('#editTipoAsignacion').val(tipo);
                $('#editMesaDisponible').data('selected', coord.mesa_num || '');
                $('#editObservacion').val(coord.observacion || '');

                refreshAssignmentUi({
                    codpuesto: '#editCodpuesto',
                    tipo: '#editTipoAsignacion',
                    mesaWrap: '#editMesaDisponibleWrap',
                    mesaSelect: '#editMesaDisponible',
                    ayuda: '#editTipoAyuda',
                    selectedMesa: () => $('#editMesaDisponible').val() || $('#editMesaDisponible').data('selected') || '',
                });

                $('#modalEditarCoordinador').modal('show');
            });

            @if($errors->any())
                $('#modalCrearCoordinador').modal('show');
            @endif
        });
    </script>
@endsection
