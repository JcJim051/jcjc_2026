@extends('adminlte::page')

@section('title', 'Previsualizar actualización personal')

@section('content_header')
    <h2>Previsualización de información personal</h2>
@stop

@section('content')
    <div class="alert alert-info">
        <strong>Las coordinaciones no se modificarán.</strong>
        Los cambios seguros vienen seleccionados. También puedes marcar manualmente correcciones de correo,
        teléfono u otro dato personal que hayas verificado.
    </div>

    <div class="row">
        <div class="col-md-3 col-6">
            <div class="small-box bg-secondary"><div class="inner"><h3>{{ $counts['rows'] }}</h3><p>Personas en archivo</p></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="small-box bg-success"><div class="inner"><h3>{{ $counts['safe'] }}</h3><p>Campos seguros</p></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="small-box bg-warning"><div class="inner"><h3>{{ $counts['conflicts'] }}</h3><p>Conflictos</p></div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="small-box bg-danger"><div class="inner"><h3>{{ $counts['not_found'] + $counts['duplicates'] }}</h3><p>No procesables</p></div></div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.abogados.actualizacion_personal.apply') }}"
          onsubmit="return confirm('¿Aplicar los cambios seleccionados? Las coordinaciones permanecerán intactas.');">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="card">
            <div class="card-body d-flex flex-wrap align-items-center" style="gap: 8px;">
                <a href="{{ route('admin.abogados.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check mr-1"></i> Aplicar cambios seleccionados
                </button>
                <span class="text-muted ml-2">
                    Los conflictos no se marcan automáticamente: selecciónalos solo si el archivo contiene el dato correcto.
                </span>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive">
            <table id="personalChangesTable" class="table table-sm table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Aplicar</th>
                        <th>Fila</th>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Campo</th>
                        <th>Valor actual</th>
                        <th>Valor del archivo</th>
                        <th>Estado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($changes as $change)
                        <tr>
                            <td class="text-center">
                                @if($change['change_id'])
                                    <input type="checkbox"
                                           name="selected_changes[]"
                                           value="{{ $change['change_id'] }}"
                                           {{ $change['status'] === 'restaurar' ? 'checked' : '' }}>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $change['row'] }}</td>
                            <td>{{ $change['cedula'] }}</td>
                            <td>{{ $change['nombre'] }}</td>
                            <td>{{ $change['field'] ?: '-' }}</td>
                            <td>{{ $change['current'] ?? '-' }}</td>
                            <td>{{ $change['new'] ?? '-' }}</td>
                            <td>
                                @if($change['status'] === 'restaurar')
                                    <span class="badge badge-success">SE RESTAURARÁ</span>
                                @elseif($change['status'] === 'conflicto')
                                    <span class="badge badge-warning">CONFLICTO</span>
                                @else
                                    <span class="badge badge-danger">{{ strtoupper($change['status']) }}</span>
                                @endif
                            </td>
                            <td>{{ $change['message'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </form>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
@endsection

@section('js')
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script>
        $(function () {
            $('#personalChangesTable').DataTable({
                responsive: true,
                paging: false,
                order: [[1, 'asc']],
                columnDefs: [{ targets: 0, orderable: false, searchable: false }],
                language: { search: 'Buscar:' }
            });
        });
    </script>
@endsection
