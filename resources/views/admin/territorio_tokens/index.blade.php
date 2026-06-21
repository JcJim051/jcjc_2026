@extends('adminlte::page')

@section('title', 'Tokens Territoriales')

@section('content_header')
    <h1 style="text-align: center">Tokens Territoriales</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Crear Token</h3>
            <div class="card-tools">
                <a href="{{ route('admin.bloqueos_mesas.index') }}" class="btn btn-sm btn-outline-primary">Ver bloqueos de mesas</a>
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.territorio_tokens.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Tipo de token</label>
                            <select name="token_mode" id="token_mode" class="form-control" required>
                                <option value="referidos">Referir + seguimiento</option>
                                <option value="consulta">Solo consulta (avance)</option>
                                <option value="reporte_operativo">Reporte operativo coordinadores</option>
                                <option value="comision_alertas">Alertas por zona electoral</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Eleccion</label>
                            <select name="eleccion_id" id="eleccion_id" class="form-control" required>
                                <option value="">Seleccione...</option>
                                @foreach ($elecciones as $e)
                                    @if($e->estado === 'activa')
                                        <option value="{{ $e->id }}" {{ (int)($eleccionId ?? 0) === (int)$e->id ? 'selected' : '' }}>
                                            {{ $e->id }} - {{ $e->nombre }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Municipio</label>
                            <select name="municipio_codigo" id="municipio_codigo" class="form-control" required></select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Comunas (opcional)</label>
                            <select name="comuna[]" id="comuna" class="form-control" multiple></select>
                        </div>
                    </div>
                </div>
                <div class="row" id="zona_wrap" style="display:none;">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Zonas</label>
                            <select name="zona[]" id="zona" class="form-control" multiple></select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6" id="consulta_municipios_wrap">
                        <div class="form-group">
                            <label>Municipios adicionales</label>
                            <select name="municipios_multi[]" id="municipios_multi" class="form-control" multiple></select>
                            <small class="text-muted d-block mt-1">Seleccionados:</small>
                            <div id="municipios_multi_preview" class="municipios-preview text-muted">Sin selección</div>
                            <button type="button" class="btn btn-xs btn-outline-primary mt-2" id="btn_select_all_municipios">Seleccionar todos</button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Responsable (opcional)</label>
                            <input type="text" name="responsable" class="form-control">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Expira (opcional)</label>
                            <input type="date" name="expires_at" class="form-control">
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Crear token</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tokens existentes</h3>
            <div class="card-tools d-flex" style="gap:8px;">
                <form action="{{ route('admin.territorio_tokens.bloquear_todos') }}" method="POST" onsubmit="return confirm('¿Bloquear todos los tokens de la eleccion seleccionada?');">
                    @csrf
                    <input type="hidden" name="eleccion_id" value="{{ $eleccionId ?? '' }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Bloquear todos</button>
                </form>
                <a href="{{ route('admin.territorio_tokens.export', ['eleccion_id' => $eleccionId ?? null]) }}" class="btn btn-sm btn-success">
                    Descargar tabla
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.territorio_tokens.index') }}" class="form-inline mb-3">
                <label class="mr-2">Eleccion operativa:</label>
                <select name="eleccion_id" class="form-control mr-2" onchange="this.form.submit()">
                    @foreach($elecciones as $e)
                        <option value="{{ $e->id }}" {{ (int)($eleccionId ?? 0) === (int)$e->id ? 'selected' : '' }}>
                            {{ $e->id }} - {{ $e->nombre }} ({{ $e->estado }})
                        </option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-primary">Filtrar</button></noscript>
            </form>
            <table id="tokensTable" class="table table-bordered table-sm display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Encargado</th>
                        <th>Municipio</th>
                        <th>Zona</th>
                        <th>Comuna</th>
                        <th>Mesas</th>
                        <th>Meta %</th>
                        <th>Meta objetivo</th>
                        <th>Meta pactada</th>
                        <th>Avance pactado</th>
                        <th>Ocupados</th>
                        <th>Referidos T/M</th>
                        <th>Faltan</th>
                        <th>Faltan pactada</th>
                        <th>Token</th>
                        <th>Activo</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $t)
                        <tr>
                            <td>{{ $t->id }}</td>
                            <td>{{ ($t->modulo_resuelto ?? null) === 'reporte_operativo' ? 'Reporte' : (($t->modulo_resuelto ?? null) === 'comision_alertas' ? 'Comisión' : ($t->es_consulta ? 'Consulta' : 'Referidos')) }}</td>
                            <td>{{ $t->responsable ?: 'N/D' }}</td>
                            <td>
                                {{ $t->municipios_label ?? ($t->departamento_nombre && $t->municipio_nombre ? ($t->departamento_nombre . ' / ' . $t->municipio_nombre) : 'N/D') }}
                            </td>
                            <td>{{ ($t->modulo_resuelto ?? null) === 'comision_alertas' ? ($t->zz_label ?? 'N/D') : '-' }}</td>
                            <td>{{ $t->comuna ?: '-' }}</td>
                            <td><span class="badge badge-info">{{ $t->mesas_total ?? 0 }}</span></td>
                            <td><span class="badge badge-dark">{{ is_null($t->meta_testigos_pct ?? null) ? '-' : (($t->meta_testigos_pct ?? 100) . '%') }}</span></td>
                            <td><span class="badge badge-primary">{{ is_null($t->meta_objetivo ?? null) ? '-' : $t->meta_objetivo }}</span></td>
                            <td><span class="badge badge-secondary">{{ is_null($t->meta_pactada ?? null) ? '-' : $t->meta_pactada }}</span></td>
                            <td>
                                @if(!is_null($t->avance_pactada_pct))
                                    <span class="badge badge-info">{{ $t->avance_pactada_pct }}%</span>
                                @else
                                    <span class="text-muted">N/D</span>
                                @endif
                            </td>
                            <td><span class="badge badge-success">{{ is_null($t->ocupados_total ?? null) ? '-' : $t->ocupados_total }}</span></td>
                            <td><span class="badge badge-info">{{ $t->referidos_token_municipio ?? '0/0' }}</span></td>
                            <td><span class="badge badge-warning">{{ is_null($t->faltan_total ?? null) ? '-' : $t->faltan_total }}</span></td>
                            <td><span class="badge badge-warning">{{ is_null($t->faltan_pactada ?? null) ? '-' : $t->faltan_pactada }}</span></td>
                            <td>
                                <small>{{ $t->token }}</small><br>
                                <small>
                                    @if(($t->modulo_resuelto ?? null) === 'reporte_operativo')
                                    <a href="{{ route('public.coordinador_reportes.identify', $t->token) }}" target="_blank">Reporte</a>
                                    @elseif(($t->modulo_resuelto ?? null) === 'comision_alertas')
                                    <a href="{{ route('public.comision_alertas.index', $t->token) }}" target="_blank">Comisión</a>
                                    @else
                                        @if(!$t->es_consulta)
                                        <a href="{{ route('public.referidos.form', $t->token) }}" target="_blank">Formulario</a> |
                                        @endif
                                        <a href="{{ route('public.referidos.seguimiento', $t->token) }}" target="_blank">Seguimiento</a>
                                    @endif
                                </small>
                            </td>
                            <td>{{ $t->activo ? 'SI' : 'NO' }}</td>
                            <td>
                                <div class="d-flex" style="gap:6px;">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-info js-open-token-edit"
                                        data-update-url="{{ route('admin.territorio_tokens.update', $t) }}"
                                        data-token-id="{{ $t->id }}"
                                        data-responsable="{{ $t->responsable }}"
                                        data-comuna="{{ $t->comuna }}"
                                        data-modulo="{{ $t->modulo_resuelto ?? '' }}"
                                        data-zz="{{ $t->zz }}"
                                        data-expires-at="{{ optional($t->expires_at)->format('Y-m-d') }}"
                                        data-activo="{{ $t->activo ? 1 : 0 }}"
                                        data-es-consulta="{{ $t->es_consulta ? 1 : 0 }}"
                                        data-eleccion-id="{{ $t->eleccion_id }}"
                                        data-municipios='@json(!empty($t->municipios) ? $t->municipios : [$t->dd . "-" . $t->mm])'
                                        data-toggle="modal"
                                        data-target="#editTokenModal">
                                        Editar
                                    </button>
                                    @if(($t->modulo_resuelto ?? null) === 'reporte_operativo')
                                        <a href="{{ route('admin.territorio_tokens.projection', ['token' => $t, 'target' => 'reporte']) }}"
                                           target="_blank"
                                           class="btn btn-sm btn-success"
                                           title="Proyectar QR para reporte operativo">
                                            <i class="fas fa-qrcode"></i> Reporte
                                        </a>
                                    @elseif(($t->modulo_resuelto ?? null) === 'comision_alertas')
                                        <a href="{{ route('admin.territorio_tokens.projection', ['token' => $t, 'target' => 'comision']) }}"
                                           target="_blank"
                                           class="btn btn-sm btn-success"
                                           title="Proyectar QR para comisión">
                                            <i class="fas fa-qrcode"></i> Comisión
                                        </a>
                                    @elseif(!$t->es_consulta)
                                        <a href="{{ route('admin.territorio_tokens.projection', ['token' => $t, 'target' => 'formulario']) }}"
                                           target="_blank"
                                           class="btn btn-sm btn-success"
                                           title="Proyectar QR para referir">
                                            <i class="fas fa-qrcode"></i> Referir
                                        </a>
                                    @endif
                                    @if(($t->modulo_resuelto ?? null) !== 'reporte_operativo')
                                    <a href="{{ route('admin.territorio_tokens.projection', ['token' => $t, 'target' => 'seguimiento']) }}"
                                       target="_blank"
                                       class="btn btn-sm btn-primary"
                                       title="Proyectar QR de seguimiento">
                                        <i class="fas fa-chart-line"></i> Avance
                                    </a>
                                    @endif
                                    <form action="{{ route('admin.territorio_tokens.toggle', $t) }}" method="POST">
                                        @csrf
                                        <button class="btn btn-sm btn-warning" type="submit">Cambiar</button>
                                    </form>
                                    <form action="{{ route('admin.territorio_tokens.destroy', $t) }}" method="POST" onsubmit="return confirm('¿Eliminar este token?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="editTokenModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title" id="editTokenModalTitle">Editar token</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editTokenForm" action="#" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Responsable</label>
                            <input type="text" name="responsable" id="edit_responsable" class="form-control">
                        </div>
                        <div class="form-group" id="edit_comuna_wrap">
                            <label>Comuna</label>
                            <input type="text" name="comuna" id="edit_comuna" class="form-control" placeholder="Ej: 01COMUNA 1,02COMUNA 2">
                        </div>
                        <div class="form-group" id="edit_zona_wrap" style="display:none;">
                            <label>Zonas</label>
                            <select name="zz[]" id="edit_zz" class="form-control" multiple></select>
                        </div>
                        <div class="form-group" id="edit_municipios_wrap" style="display:none;">
                            <label>Municipios del token</label>
                            <select name="municipios_multi[]" id="edit_municipios_multi" class="form-control" multiple></select>
                            <small class="text-muted d-block mt-1">Seleccionados:</small>
                            <div id="edit_municipios_preview" class="municipios-preview text-muted">Sin selección</div>
                        </div>
                        <div class="form-group">
                            <label>Expira</label>
                            <input type="date" name="expires_at" id="edit_expires_at" class="form-control">
                        </div>
                        <div class="form-group mb-0">
                            <label>Activo</label>
                            <select name="activo" id="edit_activo" class="form-control">
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
<style>
    .modal-backdrop {
        z-index: 1040 !important;
    }
    .modal {
        z-index: 1060 !important;
    }
    .select2-container {
        width: 100% !important;
        max-width: 100% !important;
    }
    .form-group .select2-container {
        display: block !important;
    }
    #consulta_municipios_wrap .select2-container .select2-selection--multiple {
        height: 38px;
        min-height: 38px;
        max-height: 38px;
        overflow: hidden;
        box-sizing: border-box;
        width: 100% !important;
    }
    #consulta_municipios_wrap .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        height: 36px;
        line-height: 36px;
        overflow: hidden;
        white-space: nowrap;
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        padding-right: 6px;
    }
    .modal .select2-container .select2-selection--multiple {
        height: 38px;
        min-height: 38px;
        max-height: 38px;
        overflow: hidden;
        box-sizing: border-box;
        width: 100% !important;
    }
    .modal .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        height: 36px;
        line-height: 36px;
        overflow: hidden;
        white-space: nowrap;
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        padding-right: 6px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-top: 3px;
    }
    .select2-container--default .select2-selection--multiple .select2-search--inline {
        max-width: 110px;
        overflow: hidden;
    }
    .select2-compact-summary {
        font-size: 12px;
        color: #3c4b64;
        font-weight: 600;
    }
    .municipios-preview {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        border: 1px solid #ced4da;
        border-radius: 6px;
        background: #fff;
        min-height: 38px;
        max-height: 90px;
        overflow-y: auto;
        padding: 6px 8px;
        font-size: 12px;
        line-height: 1.4;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script>
$(function(){
    $('#municipio_codigo').select2({
        placeholder: 'Seleccione municipio',
        ajax: {
            url: '{{ route('admin.territorio_tokens.municipios') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term, eleccion_id: $('#eleccion_id').val(), _token: '{{ csrf_token() }}' };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#municipios_multi').select2({
        placeholder: 'Seleccione uno o varios municipios',
        width: '100%',
        closeOnSelect: false,
        ajax: {
            url: '{{ route('admin.territorio_tokens.municipios') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term, eleccion_id: $('#eleccion_id').val(), _token: '{{ csrf_token() }}' };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#edit_municipios_multi').select2({
        placeholder: 'Seleccione uno o varios municipios',
        width: '100%',
        closeOnSelect: false,
        dropdownParent: $('#editTokenModal'),
        ajax: {
            url: '{{ route('admin.territorio_tokens.municipios') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    eleccion_id: $('#editTokenModal').data('eleccion-id')
                };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#comuna').select2({
        placeholder: 'Comuna (opcional)',
        allowClear: true,
        ajax: {
            url: '{{ route('admin.territorio_tokens.comunas') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    eleccion_id: $('#eleccion_id').val(),
                    municipio_codigo: $('#municipio_codigo').val()
                };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#zona').select2({
        placeholder: 'Seleccione una o varias zonas',
        width: '100%',
        closeOnSelect: false,
        ajax: {
            url: '{{ route('admin.territorio_tokens.zonas') }}',
            dataType: 'json',
            delay: 250,
            data: function () {
                return {
                    eleccion_id: $('#eleccion_id').val(),
                    municipio_codigo: $('#municipio_codigo').val()
                };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#edit_zz').select2({
        placeholder: 'Seleccione una o varias zonas',
        width: '100%',
        closeOnSelect: false,
        width: '100%',
        dropdownParent: $('#editTokenModal'),
        ajax: {
            url: '{{ route('admin.territorio_tokens.zonas') }}',
            dataType: 'json',
            delay: 250,
            data: function () {
                return {
                    eleccion_id: $('#editTokenModal').data('eleccion-id'),
                    municipio_codigo: $('#editTokenModal').data('municipio-codigo')
                };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#eleccion_id').on('change', function(){
        $('#municipio_codigo').val(null).trigger('change');
        $('#municipios_multi').val(null).trigger('change');
        $('#comuna').val(null).trigger('change');
        $('#zona').val(null).trigger('change');
    });

    $('#municipio_codigo').on('change', function(){
        $('#comuna').val(null).trigger('change');
        $('#zona').val(null).trigger('change');
    });

    $('#token_mode').on('change', function(){
        var isConsulta = $(this).val() === 'consulta';
        var isComision = $(this).val() === 'comision_alertas';
        $('#consulta_municipios_wrap').toggle(!isComision);
        $('#zona_wrap').toggle(isComision);
        $('#municipio_codigo').prop('required', true).closest('.form-group').parent().show();
        $('#comuna').prop('disabled', isConsulta || isComision);
        $('#zona').prop('disabled', !isComision);
        $('#municipios_multi').prop('disabled', isComision);

        if (isComision) {
            $('#municipios_multi').val(null).trigger('change');
            $('#comuna').val(null).trigger('change');
        } else {
            $('#zona').val(null).trigger('change');
        }
    }).trigger('change');

    $('#btn_select_all_municipios').on('click', function(){
        $.get('{{ route('admin.territorio_tokens.municipios') }}', {
            eleccion_id: $('#eleccion_id').val(),
            q: ''
        }).done(function(resp){
            var items = (resp && resp.results) ? resp.results : [];
            var selected = [];
            items.forEach(function(it){
                if ($('#municipios_multi option[value="' + it.id + '"]').length === 0) {
                    var newOption = new Option(it.text, it.id, false, true);
                    $('#municipios_multi').append(newOption);
                }
                selected.push(it.id);
            });
            $('#municipios_multi').val(selected).trigger('change');
        });
    });

    function compactMultiSelect($select) {
        var selectedCount = ($select.val() || []).length;
        var $container = $select.next('.select2-container');
        var $rendered = $container.find('.select2-selection__rendered');
        $rendered.find('.select2-compact-summary').remove();
        var selectedText = ($select.find('option:selected').map(function(){ return $(this).text().trim(); }).get() || []).join(' | ');
        $container.attr('title', selectedText);

        if (selectedCount > 1) {
            $rendered.find('.select2-selection__choice').hide();
            $rendered.prepend('<li class="select2-compact-summary">' + selectedCount + ' municipios seleccionados</li>');
        } else {
            $rendered.find('.select2-selection__choice').show();
        }

        var previewSelector = $select.data('preview') || '#municipios_multi_preview';
        var $preview = $(previewSelector);
        if ($preview.length) {
            if (!selectedText) {
                $preview.text('Sin selección');
            } else {
                var asList = selectedText.split(' | ').map(function (x) { return x.trim(); }).filter(Boolean);
                $preview.html(asList.join('<br>'));
            }
        }
    }

    $('#municipios_multi').on('change', function () {
        compactMultiSelect($(this));
    });

    compactMultiSelect($('#municipios_multi'));
    $('#edit_municipios_multi').on('change', function () {
        compactMultiSelect($(this));
    });

    $(document).on('click', '.js-open-token-edit', function () {
        var $btn = $(this);
        var municipios = $btn.data('municipios') || [];
        var esConsulta = String($btn.data('es-consulta')) === '1';
        var modulo = String($btn.data('modulo') || '');
        var esComision = modulo === 'comision_alertas';
        var eleccionId = $btn.data('eleccion-id');
        var $modal = $('#editTokenModal');
        var $municipios = $('#edit_municipios_multi');

        $('#editTokenForm').attr('action', $btn.data('update-url'));
        $('#editTokenModalTitle').text('Editar token #' + $btn.data('token-id'));
        $('#edit_responsable').val($btn.data('responsable') || '');
        $('#edit_comuna').val($btn.data('comuna') || '');
        $('#edit_expires_at').val($btn.data('expires-at') || '');
        $('#edit_activo').val(String($btn.data('activo')) === '0' ? '0' : '1');

        $modal.data('eleccion-id', eleccionId);
        $modal.data('municipio-codigo', municipios.length ? municipios[0] : '');
        $('#edit_comuna_wrap').toggle(!esConsulta && !esComision);
        $('#edit_comuna').prop('disabled', esConsulta || esComision);
        $('#edit_zona_wrap').toggle(esComision);
        $('#edit_zz').prop('disabled', !esComision);
        $('#edit_municipios_wrap').toggle(!esComision);

        $municipios.empty().trigger('change');
        municipios.forEach(function (geo) {
            var option = new Option(geo, geo, true, true);
            $municipios.append(option);
        });
        $municipios.trigger('change');
        compactMultiSelect($municipios);

        $('#edit_zz').empty().trigger('change');
        if (esComision && $btn.data('zz')) {
            String($btn.data('zz')).split(',').map(function (item) { return item.trim(); }).filter(Boolean).forEach(function (zonaValue) {
                var zonaOption = new Option('Zona ' + zonaValue, zonaValue, true, true);
                $('#edit_zz').append(zonaOption);
            });
            $('#edit_zz').trigger('change');
        }
    });

    $('#tokensTable').DataTable({
        pageLength: 25,
        responsive: true,
        scrollX: true,
        order: [[0, 'desc']],
        columnDefs: [
            { responsivePriority: 1, targets: [0, 3, 5, 9] },
            { responsivePriority: 2, targets: [6, 7, 8] }
        ],
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_',
            paginate: { previous: 'Anterior', next: 'Siguiente' }
        }
    });
});
</script>
@endsection
