@extends('adminlte::page')

@section('title', 'DIVIPOL por elección')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2 class="mb-1">DIVIPOL por elección</h2>
            <p class="text-muted mb-0">Consulta, descarga y actualiza la información territorial de cada elección activa.</p>
        </div>
        @if($eleccion)
            <a href="{{ route('admin.divipol.export', $eleccion) }}" class="btn btn-success">
                <i class="fas fa-file-excel mr-1"></i> Descargar DIVIPOL
            </a>
        @endif
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success"><strong>{{ session('success') }}</strong></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><strong>{{ session('error') }}</strong></div>
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
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <form method="GET" action="{{ route('admin.divipol.index') }}" id="electionForm">
                        <div class="form-group mb-md-0">
                            <label for="eleccion_id">Elección activa</label>
                            <select name="eleccion_id" id="eleccion_id" class="form-control">
                                @forelse($elecciones as $item)
                                    <option value="{{ $item->id }}" {{ optional($eleccion)->id === $item->id ? 'selected' : '' }}>
                                        {{ $item->nombre }}{{ $item->fecha ? ' · ' . $item->fecha : '' }}
                                    </option>
                                @empty
                                    <option value="">No hay elecciones activas</option>
                                @endforelse
                            </select>
                        </div>
                    </form>
                </div>

                @if($eleccion)
                    <div class="col-md-7">
                        <form method="POST" action="{{ route('admin.divipol.update', $eleccion) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row align-items-end">
                                <div class="col-md-8">
                                    <div class="form-group mb-md-0">
                                        <label for="divipol">Actualizar DIVIPOL de {{ $eleccion->nombre }}</label>
                                        <input type="file" name="divipol" id="divipol" class="form-control" accept=".xlsx" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-upload mr-1"></i> Cargar actualización
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($eleccion)
        <div class="row mb-3">
            <div class="col-sm-4">
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-map-marker-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Puestos cargados</span>
                        <span class="info-box-number">{{ number_format($puestos->count()) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-layer-group"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Con comuna</span>
                        <span class="info-box-number">{{ number_format($puestos->whereNotNull('comuna')->filter(fn($p) => trim((string) $p->comuna) !== '')->count()) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-map-signs"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Con dirección</span>
                        <span class="info-box-number">{{ number_format($puestos->whereNotNull('direccion')->filter(fn($p) => trim((string) $p->direccion) !== '')->count()) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><strong>{{ $eleccion->nombre }}</strong></h3>
            </div>
            <div class="card-body table-responsive">
                <table id="divipolTable" class="table table-bordered table-striped table-sm nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>DD</th>
                            <th>MM</th>
                            <th>ZZ</th>
                            <th>PP</th>
                            <th>Código</th>
                            <th>Departamento</th>
                            <th>Municipio</th>
                            <th>Puesto</th>
                            <th>Comuna</th>
                            <th>Dirección</th>
                            <th>Mesas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($puestos as $puesto)
                            <tr>
                                <td>{{ $puesto->dd }}</td>
                                <td>{{ $puesto->mm }}</td>
                                <td>{{ $puesto->zz }}</td>
                                <td>{{ $puesto->pp }}</td>
                                <td>{{ $puesto->codigo_puesto ?: $puesto->dd.$puesto->mm.$puesto->zz.$puesto->pp }}</td>
                                <td>{{ $puesto->departamento }}</td>
                                <td>{{ $puesto->municipio }}</td>
                                <td>{{ $puesto->puesto }}</td>
                                <td>{{ $puesto->comuna ?: '-' }}</td>
                                <td>{{ $puesto->direccion ?: '-' }}</td>
                                <td>{{ $puesto->mesas_total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="alert alert-info">No existen elecciones activas para consultar.</div>
    @endif
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
        $(function () {
            $('#eleccion_id').on('change', function () {
                $('#electionForm').submit();
            });

            if ($('#divipolTable').length) {
                $('#divipolTable').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[6, 'asc'], [2, 'asc'], [3, 'asc']],
                    language: {
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_ registros',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ puestos',
                        infoEmpty: 'No hay puestos cargados',
                        zeroRecords: 'No se encontraron coincidencias',
                        paginate: {
                            previous: 'Anterior',
                            next: 'Siguiente'
                        }
                    }
                });
            }
        });
    </script>
@stop
