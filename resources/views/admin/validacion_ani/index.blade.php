@section('plugins.Datatables', true)
@extends('adminlte::page')

@section('title', 'Validacion ANI')

@section('content_header')
    <h1 style="text-align: center">Validacion ANI</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="mb-3 text-right">
                <form action="{{ route('admin.validacion_ani.validar_masivo') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Validar masivamente todos los pendientes por validar en ANI?')">
                        Validar masivo
                    </button>
                </form>
            </div>
            <table id="example"
                class="table display nowrap table-bordered"
                style="width:100%; font-size:10px">
                <thead class="text-white" style="background-color:hsl(209, 36%, 54%)">
                    <tr>
                        <th>ID</th>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Municipio</th>
                        <th>Puesto</th>
                        <th>Mesa</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>PDF</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($referidos as $r)
                        @php
                            $ok = in_array($r->estado, ['validado', 'acreditado'], true);
                            $color = $ok ? 'rgb(0,169,14)' : 'red';
                        @endphp
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>{{ $r->cedula }}</td>
                            <td>{{ $r->nombre }}</td>
                            <td>{{ $r->email }}</td>
                            <td style="color: {{ $color }}">{{ $r->municipio }}</td>
                            <td style="color: {{ $color }}">{{ $r->puesto }}</td>
                            <td style="color: {{ $color }}">{{ $r->mesa_num }}</td>
                            <td>
                                @if(($r->tipo_fila ?? 'referido') === 'coordinador')
                                    <span class="badge badge-info">Coordinador Rem</span>
                                @else
                                    <span class="badge badge-secondary">Referido</span>
                                @endif
                            </td>
                            <td style="font-size:20px; text-align:center">
                                @if($ok)
                                    <i class="fas fa-vote-yea" style="color:rgb(22,161,22)">
                                        <p hidden>Validado</p>
                                    </i>
                                @else
                                    <i class="fas fa-window-close" style="color:rgb(235,62,10)">
                                        <p hidden>Pendiente</p>
                                    </i>
                                @endif
                            </td>
                            <td>
                                @if(!empty($r->cedula_pdf_path))
                                    <a class="btn btn-sm btn-info" href="{{ asset('storage/' . $r->cedula_pdf_path) }}" target="_blank">
                                        Ver PDF
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if(($r->tipo_fila ?? 'referido') === 'coordinador')
                                    @php
                                        $okCoord = in_array($r->estado, ['validado', 'acreditado'], true);
                                    @endphp
                                    <a class="btn btn-sm {{ $okCoord ? 'btn-success' : 'btn-primary' }}"
                                        href="{{ route('admin.validacion_ani.coordinador.edit', $r->coordinacion_id) }}">
                                        {{ $okCoord ? 'Ver' : 'Validar' }}
                                    </a>
                                @else
                                    <a class="btn btn-sm {{ $ok ? 'btn-success' : 'btn-primary' }}"
                                        href="{{ route('admin.validacion_ani.edit', $r->id) }}">
                                        {{ $ok ? 'Ver' : 'Validar' }}
                                    </a>
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
