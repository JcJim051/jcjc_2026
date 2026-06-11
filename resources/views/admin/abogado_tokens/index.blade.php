@extends('adminlte::page')

@section('title', 'Enlaces de abogados')

@section('content_header')
    <div>
        <h2 class="mb-1">Enlaces de caracterización</h2>
        <p class="text-muted mb-0">Genera accesos temporales para nuevos integrantes o para actualizar datos existentes.</p>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success"><strong>{{ session('success') }}</strong></div>
    @endif

    @if(session('generated_url'))
        <div class="alert alert-info">
            <strong>Enlace generado:</strong>
            <div class="input-group mt-2">
                <input type="text" id="generatedUrl" class="form-control" value="{{ session('generated_url') }}" readonly>
                <div class="input-group-append">
                    <button class="btn btn-info" type="button" onclick="copyLink('generatedUrl')">
                        <i class="fas fa-copy mr-1"></i> Copiar
                    </button>
                    @if(session('generated_projection_url'))
                        <a href="{{ session('generated_projection_url') }}" target="_blank" class="btn btn-success">
                            <i class="fas fa-qrcode mr-1"></i> Proyectar QR
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title">Crear enlace</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.abogado_tokens.store') }}">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-4 form-group">
                        <label>Tipo de enlace</label>
                        <select name="type" id="tokenType" class="form-control" required>
                            <option value="characterization" {{ old('type') === 'characterization' ? 'selected' : '' }}>Nueva caracterización</option>
                            <option value="update" {{ old('type') === 'update' ? 'selected' : '' }}>Actualización por cédula</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Nombre o proceso</label>
                        <input type="text" name="label" class="form-control" value="{{ old('label') }}" placeholder="Ej: Equipo segunda vuelta">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Vigencia en horas</label>
                        <input type="number" name="hours" id="tokenHours" class="form-control" value="{{ old('hours', 1) }}" min="1" max="720" required>
                    </div>
                    <div class="col-md-2 form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-link mr-1"></i> Generar
                        </button>
                    </div>
                </div>
                <small class="text-muted">
                    Ambos enlaces permiten múltiples usos durante su vigencia. La actualización exige validar también la cédula.
                </small>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Enlaces generados</h3></div>
        <div class="card-body table-responsive">
            <table id="tokensTable" class="table table-bordered table-striped table-sm nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Nombre</th>
                        <th>Enlace</th>
                        <th>Expira</th>
                        <th>Usos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokens as $token)
                        @php
                            $available = $token->isAvailable();
                            $inputId = 'tokenUrl' . $token->id;
                        @endphp
                        <tr>
                            <td>{{ $token->id }}</td>
                            <td>{{ $token->type === 'characterization' ? 'Caracterización' : 'Actualización' }}</td>
                            <td>{{ $token->label ?: '-' }}</td>
                            <td style="min-width:330px;">
                                <div class="input-group input-group-sm">
                                    <input id="{{ $inputId }}" type="text" class="form-control" value="{{ $token->publicUrl() }}" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-info" onclick="copyLink('{{ $inputId }}')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <a href="{{ $token->publicUrl() }}" target="_blank" class="btn btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td>{{ optional($token->expires_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $token->completed_count }}</td>
                            <td>
                                @if($available)
                                    <span class="badge badge-success">Disponible</span>
                                @elseif($token->expires_at->isPast())
                                    <span class="badge badge-secondary">Vencido</span>
                                @elseif($token->used_at)
                                    <span class="badge badge-info">Utilizado</span>
                                @else
                                    <span class="badge badge-danger">Inactivo</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex" style="gap:6px;">
                                    <form method="POST" action="{{ route('admin.abogado_tokens.toggle', $token) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-warning" type="submit">
                                            {{ $token->active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.abogado_tokens.projection', $token) }}"
                                       target="_blank"
                                       class="btn btn-sm btn-success"
                                       title="Abrir QR para proyectar">
                                        <i class="fas fa-qrcode mr-1"></i> QR
                                    </a>
                                    <form method="POST" action="{{ route('admin.abogado_tokens.destroy', $token) }}" onsubmit="return confirm('¿Eliminar este enlace?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
@stop

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script>
        function copyLink(id) {
            const input = document.getElementById(id);
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value);
        }

        $(function () {
            $('#tokenType').on('change', function () {
                $('#tokenHours').val(this.value === 'characterization' ? 1 : 72);
            });

            $('#tokensTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: 'Buscar:',
                    zeroRecords: 'No se encontraron enlaces',
                    paginate: { previous: 'Anterior', next: 'Siguiente' }
                }
            });
        });
    </script>
@stop
