@extends('adminlte::page')

@section('title', 'Abogados')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h2>Caracterización de Abogados</h2>
        <a href="{{ route('admin.abogados.create') }}" class="btn btn-primary btn-sm">Crear nuevo</a>
    </div>
@stop

@section('content')
    @if (session('info'))
        <div class="alert alert-success"><strong>{{ session('info') }}</strong></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><strong>Importar desde Google Sheets</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.abogados.import_sheet') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>URL del Spreadsheet</label>
                        <input type="url" name="spreadsheet_url" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>GID (opcional)</label>
                        <input type="text" name="gid" class="form-control" placeholder="631350820">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>CSV (alterno)</label>
                        <input type="file" name="csv_file" class="form-control-file" accept=".csv,.txt">
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Importar</button>
                    </div>
                </div>
                <small class="text-muted">Usa URL o CSV. Si el servidor no sale a Google, sube CSV exportado.</small>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-sm" id="abogadosTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Activo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($abogados as $abogado)
                        <tr>
                            <td>{{ $abogado->id }}</td>
                            <td>{{ $abogado->nombre }}</td>
                            <td>{{ $abogado->telefono ?? '-' }}</td>
                            <td>{{ $abogado->correo ?? '-' }}</td>
                            <td>{!! $abogado->activo ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-secondary">No</span>' !!}</td>
                            <td>
                                <a href="{{ route('admin.abogados.show', $abogado->id) }}" class="btn btn-success btn-sm">Ver</a>
                                <a href="{{ route('admin.abogados.edit', $abogado->id) }}" class="btn btn-primary btn-sm">Editar</a>
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
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#abogadosTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'asc']],
                language: {
                    search: 'Buscar:'
                }
            });
        });
    </script>
@endsection
