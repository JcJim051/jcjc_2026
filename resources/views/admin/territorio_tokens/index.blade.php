@extends('adminlte::page')

@section('title', 'Tokens Territoriales')

@section('content_header')
    <h1 style="text-align: center">Tokens Territoriales</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Crear Token</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.territorio_tokens.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Eleccion</label>
                            <select name="eleccion_id" id="eleccion_id" class="form-control" required>
                                <option value="">Seleccione...</option>
                                @foreach ($elecciones as $e)
                                    <option value="{{ $e->id }}">{{ $e->id }} - {{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Municipio</label>
                            <select name="municipio_codigo" id="municipio_codigo" class="form-control" required></select>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Comuna (opcional)</label>
                            <select name="comuna" id="comuna" class="form-control"></select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Responsable (opcional)</label>
                            <input type="text" name="responsable" class="form-control">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Expira (opcional)</label>
                            <input type="date" name="expires_at" class="form-control">
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Crear token</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tokens existentes</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Eleccion</th>
                        <th>Territorio</th>
                        <th>Municipio</th>
                        <th>Comuna</th>
                        <th>Mesas</th>
                        <th>Referidos</th>
                        <th>Token</th>
                        <th>Activo</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $t)
                        <tr>
                            <td>{{ $t->id }}</td>
                            <td>{{ $t->eleccion_id }}</td>
                            <td>{{ $t->dd }}-{{ $t->mm }}</td>
                            <td>
                                {{ $t->departamento_nombre && $t->municipio_nombre ? ($t->departamento_nombre . ' / ' . $t->municipio_nombre) : 'N/D' }}
                            </td>
                            <td>{{ $t->comuna }}</td>
                            <td><span class="badge badge-info">{{ $t->mesas_total ?? 0 }}</span></td>
                            <td><span class="badge badge-success">{{ $t->referidos_total ?? 0 }}</span></td>
                            <td>
                                <small>{{ $t->token }}</small><br>
                                <small>
                                    <a href="{{ route('public.referidos.form', $t->token) }}" target="_blank">Formulario</a> |
                                    <a href="{{ route('public.referidos.seguimiento', $t->token) }}" target="_blank">Seguimiento</a>
                                </small>
                            </td>
                            <td>{{ $t->activo ? 'SI' : 'NO' }}</td>
                            <td>
                                <div class="d-flex" style="gap:6px;">
                                    <form action="{{ route('admin.territorio_tokens.toggle', $t) }}" method="POST">
                                        @csrf
                                        <button class="btn btn-sm btn-warning" type="submit">Cambiar</button>
                                    </form>
                                    <form action="{{ route('admin.territorio_tokens.destroy', $t) }}" method="POST" onsubmit="return confirm('¿Eliminar este token?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">Borrar</button>
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

@section('js')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    $('#municipio_codigo').select2({
        placeholder: 'Seleccione municipio',
        ajax: {
            url: '{{ route('admin.territorio_tokens.municipios') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term, eleccion_id: $('#eleccion_id').val(), _token: '{{ csrf_token() }}' };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#comuna').select2({
        placeholder: 'Comuna (opcional)',
        allowClear: true,
        ajax: {
            url: '{{ route('admin.territorio_tokens.comunas') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    eleccion_id: $('#eleccion_id').val(),
                    municipio_codigo: $('#municipio_codigo').val()
                };
            },
            processResults: function (data) {
                return data;
            }
        }
    });

    $('#eleccion_id').on('change', function(){
        $('#municipio_codigo').val(null).trigger('change');
        $('#comuna').val(null).trigger('change');
    });

    $('#municipio_codigo').on('change', function(){
        $('#comuna').val(null).trigger('change');
    });
});
</script>
@endsection
