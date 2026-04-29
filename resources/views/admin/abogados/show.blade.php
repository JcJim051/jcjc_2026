@extends('adminlte::page')

@section('title', 'Ver Abogado')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h2>Ver Abogado e Historial de Reuniones</h2>
        <a href="{{ route('admin.abogados.edit', $abogado->id) }}" class="btn btn-primary btn-sm">Editar</a>
    </div>
@stop

@section('content')
    @php
        $honorariosRaw = $abogado->honorarios;
        $honorariosNum = null;
        if (!is_null($honorariosRaw) && $honorariosRaw !== '') {
            $normalized = preg_replace('/[^\d,.-]/', '', (string) $honorariosRaw);
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
            if (is_numeric($normalized)) {
                $honorariosNum = (float) $normalized;
            }
        }
    @endphp
    <style>
        .info-grid-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
            box-shadow: 0 2px 10px rgba(16, 24, 40, .04);
            height: 100%;
        }
        .info-grid-card .label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
            font-weight: 700;
        }
        .info-grid-card .value {
            font-size: 15px;
            color: #1f2937;
            font-weight: 600;
            line-height: 1.35;
            word-break: break-word;
        }
        .section-title {
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
        }
    </style>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="section-title">Información Personal</div>
                    <div class="row">
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Nombre</div><div class="value">{{ $abogado->nombre }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Cédula</div><div class="value">{{ $abogado->cc ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Teléfono</div><div class="value">{{ $abogado->telefono ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Correo</div><div class="value">{{ $abogado->correo ?? '-' }}</div></div></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Comuna</div><div class="value">{{ $abogado->comuna ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Puesto</div><div class="value">{{ $abogado->puesto ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Mesa</div><div class="value">{{ $abogado->mesa ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Disponibilidad</div><div class="value">{{ $abogado->disponibilidad ?? '-' }}</div></div></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <div class="info-grid-card">
                                <div class="label">Dirección</div>
                                <div class="value text-break">{{ $abogado->direccion ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Estudios</div><div class="value">{{ $abogado->estudios ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Título</div><div class="value">{{ $abogado->titulo ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Entidad</div><div class="value">{{ $abogado->entidad ?? '-' }}</div></div></div>
                        <div class="col-md-3 mb-2"><div class="info-grid-card"><div class="label">Secretaría</div><div class="value">{{ $abogado->secretaria ?? '-' }}</div></div></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3 mb-2">
                            <div class="info-grid-card">
                                <div class="label">Honorarios</div>
                                <div class="value">{{ !is_null($honorariosNum) ? '$'.number_format($honorariosNum, 0, ',', '.') : ($abogado->honorarios ?? '-') }}</div>
                            </div>
                        </div>
                        <div class="col-md-9 mb-2">
                            <div class="info-grid-card">
                                <div class="label">Observación</div>
                                <div class="value">{{ $abogado->observacion ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    @if($abogado->foto)
                        <img src="{{ asset('storage/'.$abogado->foto) }}" alt="Foto" style="max-width: 220px; max-height: 220px;">
                    @endif
                    <div class="mt-2">
                        @if($abogado->pdf_cc)
                            <a href="{{ asset('storage/'.$abogado->pdf_cc) }}" target="_blank" class="btn btn-outline-primary btn-sm">Ver PDF Cédula</a>
                        @endif
                    </div>
                    <div class="mt-2">
                        {!! $abogado->activo ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>' !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Asignar Como Coordinador (Cupos Remanentes)</strong>
        </div>
        <div class="card-body">
            <p class="mb-2 text-muted">
                Puede asignarse al puesto donde vota o a cualquier puesto del municipio <strong>{{ $municipioVota ?? 'N/D' }}</strong>.
            </p>
            <form method="POST" action="{{ route('admin.abogados.asignar_coordinador', $abogado->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="eleccion_id">Elección / Campaña</label>
                        <select name="eleccion_id" id="eleccion_id" class="form-control js-eleccion-select" required>
                            <option value="">Seleccione elección...</option>
                            @foreach(($elecciones ?? collect()) as $e)
                                <option value="{{ $e->id }}" {{ (string) old('eleccion_id', $eleccionActiva) === (string) $e->id ? 'selected' : '' }}>
                                    #{{ $e->id }} - {{ $e->nombre }} {{ $e->tipo ? '(' . $e->tipo . ')' : '' }} {{ $e->estado === 'activa' ? '[Activa]' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('eleccion_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="col-md-8 form-group">
                        <label for="codpuesto">Puesto de votación</label>
                        <select name="codpuesto" id="codpuesto" class="form-control js-codpuesto-select" required>
                            <option value="">Seleccione puesto...</option>
                            @foreach(($puestosMunicipio ?? collect()) as $p)
                                <option value="{{ $p->codpuesto }}" {{ old('codpuesto') == $p->codpuesto ? 'selected' : '' }}>
                                    {{ $p->label }} | Rem: {{ $p->ocupados_rem }}/{{ $p->total_rem }} | Disp: {{ $p->disponibles_rem }}{{ $p->es_puesto_vota ? ' (Donde vota)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('codpuesto')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="col-md-4 form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Asignar Coordinador</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Historial De Coordinaciones Por Campaña</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Fecha asignación</th>
                        <th>Elección</th>
                        <th>Puesto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($historialCoordinaciones ?? collect()) as $h)
                        <tr>
                            <td>{{ $h->assigned_at ? \Carbon\Carbon::parse($h->assigned_at)->format('Y-m-d H:i') : '-' }}</td>
                            <td>{{ $h->eleccion_nombre ?? ('Elección #' . $h->eleccion_id) }}</td>
                            <td>{{ trim(($h->puesto_municipio ?? '') . ' - ' . ($h->puesto_nombre ?? $h->codpuesto), ' -') }}</td>
                            <td>
                                @if($h->released_at)
                                    <span class="badge badge-secondary">Liberado</span>
                                @else
                                    <span class="badge badge-success">Activo</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Sin historial de coordinaciones.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Historial de Reuniones</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Lugar</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($abogado->reuniones as $reunion)
                        <tr>
                            <td>{{ $reunion->id }}</td>
                            <td>{{ $reunion->fecha }}</td>
                            <td>{{ $reunion->hora_inicio ?? '-' }}</td>
                            <td>{{ $reunion->hora_fin ?? '-' }}</td>
                            <td>{{ $reunion->lugar ?? '-' }}</td>
                            <td>{{ $reunion->motivo ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">Sin reuniones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            $('.js-codpuesto-select').select2({
                width: '100%',
                placeholder: 'Buscar puesto por código o nombre...',
                allowClear: false
            });
            $('.js-eleccion-select').select2({
                width: '100%',
                placeholder: 'Seleccione elección...',
                allowClear: false
            });
        });
    </script>
@stop
