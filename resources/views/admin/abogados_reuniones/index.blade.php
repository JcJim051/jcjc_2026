@extends('adminlte::page')

@section('title', 'Admin')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h2>Reuniones de Campaña (Historial de Asistencia)</h2>
        <a href="{{ route('admin.abogados_reuniones.create') }}" class="btn btn-primary btn-sm">Crear Reunion + QR</a>
    </div>
@stop

@section('content')
    @if (session('info'))
        <div class="alert alert-success"><strong>{{ session('info') }}</strong></div>
    @endif
    <div class="card">
        <div class="card-body">
            <table id="example" class="display responsive nowrap" style="width:99%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Hora inicio</th>
                        <th>Hora fin</th>
                        <th>Lugar</th>
                        <th>Aforo</th>
                        <th>Panel QR</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reuniones as $reunion)
                        <tr>
                            <td>{{ $reunion->id }}</td>
                            <td>{{ $reunion->fecha }}</td>
                            <td>{{ $reunion->hora_inicio ?? '-' }}</td>
                            <td>{{ $reunion->hora_fin ?? '-' }}</td>
                            <td>{{ $reunion->lugar ?? '-' }}</td>
                            <td>{{ $reunion->aforo ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.abogados_reuniones.qr', $reunion->id) }}" class="btn btn-success btn-sm">
                                    Abrir panel
                                </a>
                            </td>
                            <td></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">Sin reuniones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#example').DataTable({
                pageLength: 25
            });
        });
    </script>
@endsection
