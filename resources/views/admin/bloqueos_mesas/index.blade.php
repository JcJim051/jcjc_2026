@section('plugins.Datatables', true)
@extends('adminlte::page')

@section('title', 'Bloqueos de Mesas')

@section('content_header')
    <h1 style="text-align: center">Bloqueos de Mesas</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success"><strong>{{ session('success') }}</strong></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger"><strong>{{ session('error') }}</strong></div>
    @endif
    @if (session('import_errors'))
        <div class="alert alert-warning">
            <strong>Filas con error en importación:</strong>
            <ul class="mb-0 mt-2">
                @foreach (array_slice(session('import_errors'), 0, 20) as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            @if (count(session('import_errors')) > 20)
                <small>Se muestran solo los primeros 20 errores.</small>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="mb-3 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalImportarBloqueos">
                    Importar Excel
                </button>
            </div>
            <form method="GET" action="{{ route('admin.bloqueos_mesas.index') }}" class="form-inline mb-3">
                <label class="mr-2">Elección:</label>
                <select name="eleccion_id" class="form-control mr-2">
                    <option value="0">Todas</option>
                    @foreach($elecciones as $e)
                        <option value="{{ $e->id }}" {{ (int)$eleccionId === (int)$e->id ? 'selected' : '' }}>
                            {{ $e->id }} - {{ $e->nombre }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>

            <table id="bloqueosTable" class="table display nowrap table-bordered" style="width:100%; font-size:10px">
                <thead class="text-white" style="background-color:hsl(209, 36%, 54%)">
                <tr>
                    <th>ID</th>
                    <th>Elección</th>
                    <th>Departamento</th>
                    <th>Municipio</th>
                    <th>Comuna</th>
                    <th>Puesto</th>
                    <th>Mesa</th>
                    <th>Origen</th>
                    <th>Lote</th>
                    <th>Fila</th>
                    <th>Fecha</th>
                    <th>Acción</th>
                </tr>
                </thead>
                <tbody>
                @foreach($bloqueos as $b)
                    <tr>
                        <td>{{ $b->id }}</td>
                        <td>{{ $b->eleccion }}</td>
                        <td>{{ $b->departamento }}</td>
                        <td>{{ $b->municipio }}</td>
                        <td>{{ $b->comuna }}</td>
                        <td>{{ $b->puesto }}</td>
                        <td>{{ $b->mesa_num }}</td>
                        <td>{{ $b->origen }}</td>
                        <td>{{ $b->lote ?: '-' }}</td>
                        <td>{{ $b->fila_origen ?: '-' }}</td>
                        <td>{{ $b->created_at }}</td>
                        <td>
                            <form action="{{ route('admin.bloqueos_mesas.destroy', $b->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('¿Desbloquear esta mesa?')">Desbloquear</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalImportarBloqueos" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title">Importar Mesas a Bloquear</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.bloqueos_mesas.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Elección</label>
                            <select name="eleccion_id" class="form-control" required>
                                <option value="">Seleccione...</option>
                                @foreach($elecciones as $e)
                                    <option value="{{ $e->id }}" {{ (int)$eleccionId === (int)$e->id ? 'selected' : '' }}>
                                        {{ $e->id }} - {{ $e->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Archivo Excel (xlsx/xls)</label>
                            <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls" required>
                        </div>
                        <div class="form-group">
                            <label>Modo de importación</label>
                            <select name="modo_importacion" class="form-control" required>
                                <option value="ubicacion">Ubicación en mesa (columna M)</option>
                                <option value="cant_u_objetivo">Ajuste por CANT U (bloquear adicionales)</option>
                            </select>
                            <small class="text-muted">CANT U toma la cantidad exacta a dejar habilitada por puesto y bloquea solo mesas que aún estén libres.</small>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="replace_existing" name="replace_existing" value="1">
                            <label class="form-check-label" for="replace_existing">Reemplazar bloqueos externos existentes de esta elección</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
    <script>
        $(function () {
            $('#bloqueosTable').DataTable({
                pageLength: 25,
                responsive: true,
                language: { search: 'Buscar:' }
            });
        });
    </script>
@endsection
