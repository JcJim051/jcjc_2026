@section('plugins.Datatables', true)
@extends('adminlte::page')

@section('title', 'Referidos')

@section('content_header')
    <h1 style="text-align: center">Referidos</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">
            <strong>{{ session('error') }}</strong>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <table id="example"
                class="table display nowrap table-bordered"
                style="width:100%; font-size:10px">
                <thead class="text-white" style="background-color:hsl(209, 36%, 54%)">
                    <tr>
                        <th>ID</th>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Referido por</th>
                        <th>Municipio</th>
                        <th>Puesto</th>
                        <th>Mesa</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($referidos as $r)
                        @php
                            $origen = $r->responsable ? $r->responsable : null;
                            if (!$origen && $r->comuna) {
                                $origen = 'Comuna ' . $r->comuna;
                            }
                            if (!$origen) {
                                $origen = 'Municipio ' . $r->municipio;
                            }
                        @endphp
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>{{ $r->cedula }}</td>
                            <td>{{ $r->nombre }}</td>
                            <td>{{ $r->email }}</td>
                            <td>{{ $origen }}</td>
                            <td>{{ $r->municipio }}</td>
                            <td>{{ $r->puesto }}</td>
                            <td>{{ $r->mesa_num }}</td>
                            <td>{{ $r->estado }}</td>
                            <td>
                                @if ($r->estado === 'referido')
                                    <a class="btn btn-sm btn-primary" href="{{ route('admin.referidos.asignar.form', $r->id) }}">Asignar</a>
                                @elseif ($r->estado === 'asignado')
                                    <form action="{{ route('admin.referidos.liberar', $r->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-sm btn-warning"
                                            onclick="return confirm('Esta accion liberara la mesa y devolvera el referido a estado \"referido\". Deseas continuar?')">
                                            Liberar mesa
                                        </button>
                                    </form>
                                @elseif ($r->estado === 'validado')
                                    <a class="btn btn-sm btn-success" href="{{ route('admin.validacion_ani.edit', $r->id) }}">Ver</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
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
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script>
    $(document).ready(function () {
        $('#example').DataTable({
            pageLength: 25,
            responsive: true,
            language: {
                search: "Buscar:"
            }
        });
    });
</script>
@endsection
