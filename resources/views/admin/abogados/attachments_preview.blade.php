@extends('adminlte::page')

@section('title', 'Adjuntos de abogados')

@section('content_header')
    <h2>Previsualización de fotos y cédulas</h2>
@stop

@section('content')
    <div class="alert alert-info">
        Este proceso cruza exclusivamente por cédula. No modifica información personal, coordinaciones ni elecciones.
        Cada archivo será validado antes de guardarse.
    </div>

    <div class="row">
        <div class="col-md-3 col-6"><div class="small-box bg-secondary"><div class="inner"><h3>{{ $counts['rows'] }}</h3><p>Filas</p></div></div></div>
        <div class="col-md-3 col-6"><div class="small-box bg-success"><div class="inner"><h3>{{ $counts['ready'] }}</h3><p>Listas</p></div></div></div>
        <div class="col-md-3 col-6"><div class="small-box bg-danger"><div class="inner"><h3>{{ $counts['not_found'] }}</h3><p>No encontradas</p></div></div></div>
        <div class="col-md-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3>{{ $counts['without_links'] }}</h3><p>Sin enlaces</p></div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.abogados.adjuntos.process') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <a href="{{ route('admin.abogados.index') }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-success" type="submit">
                    <i class="fas fa-cloud-download-alt mr-1"></i> Iniciar descarga validada
                </button>
                @if($replaceExisting)
                    <span class="badge badge-warning ml-2">Reemplazará adjuntos existentes</span>
                @endif
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered table-striped" id="attachmentsTable">
                <thead><tr><th>Fila</th><th>Cédula</th><th>Nombre</th><th>Foto origen</th><th>PDF origen</th><th>Foto actual</th><th>PDF actual</th><th>Estado</th></tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['row'] }}</td>
                        <td>{{ $item['cedula'] }}</td>
                        <td>{{ $item['nombre'] ?: '-' }}</td>
                        <td>{{ $item['photo_url'] ? 'Sí' : 'No' }}</td>
                        <td>{{ $item['pdf_url'] ? 'Sí' : 'No' }}</td>
                        <td><span class="badge badge-{{ $item['valid_photo'] ? 'success' : 'secondary' }}">{{ $item['valid_photo'] ? 'Válida' : 'Ausente/incorrecta' }}</span></td>
                        <td><span class="badge badge-{{ $item['valid_pdf'] ? 'success' : 'secondary' }}">{{ $item['valid_pdf'] ? 'Válido' : 'Ausente/incorrecto' }}</span></td>
                        <td>{{ $item['message'] }}</td>
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script>
        $(function () {
            $('#attachmentsTable').DataTable({ responsive: true, pageLength: 25, order: [[0, 'asc']], language: { search: 'Buscar:' } });
        });
    </script>
@endsection
