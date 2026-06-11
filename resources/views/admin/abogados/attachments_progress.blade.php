@extends('adminlte::page')

@section('title', 'Procesar adjuntos')

@section('content_header')
    <h2>Recuperación de fotos y cédulas</h2>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>{{ $processed }} de {{ $total }} filas procesadas</h4>
            <div class="progress mb-3" style="height: 22px;">
                <div class="progress-bar bg-success" style="width: {{ $total ? round(($processed / $total) * 100) : 100 }}%">
                    {{ $total ? round(($processed / $total) * 100) : 100 }}%
                </div>
            </div>
            @if(!$finished)
                <form method="POST" action="{{ route('admin.abogados.adjuntos.process') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button class="btn btn-primary" type="submit">Procesar siguiente lote</button>
                </form>
            @else
                <div class="alert alert-success">Proceso finalizado. Los archivos válidos ya reposan en el servidor.</div>
                <a href="{{ route('admin.abogados.index') }}" class="btn btn-primary">Volver a abogados</a>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-bordered">
                <thead><tr><th>Cédula</th><th>Nombre</th><th>Resultado</th><th>Detalle</th></tr></thead>
                <tbody>
                @foreach(($batch['results'] ?? []) as $index => $result)
                    @php($item = $batch['items'][(int) $index])
                    <tr>
                        <td>{{ $item['cedula'] }}</td>
                        <td>{{ $item['nombre'] ?: '-' }}</td>
                        <td><span class="badge badge-{{ $result['status'] === 'actualizado' ? 'success' : ($result['status'] === 'error' ? 'danger' : 'secondary') }}">{{ strtoupper($result['status']) }}</span></td>
                        <td>{{ implode(' ', $result['messages'] ?? []) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop
