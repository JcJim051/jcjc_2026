@extends('adminlte::page')

@section('title', 'Asignar Referido')

@section('content_header')
    <h1>Asignar Referido</h1>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <p><strong>Persona:</strong> {{ $persona->nombre ?? '' }} ({{ $persona->cedula ?? '' }})</p>
            <p><strong>Puesto actual:</strong> {{ $puesto->puesto ?? '' }} - {{ $puesto->municipio ?? '' }}</p>
            <p><strong>Mesas totales del puesto:</strong> 1 a <span id="mesas_max">{{ $puesto->mesas_total ?? 0 }}</span></p>
            @if (!empty($mesaSugerida))
                @php
                    $alertClass = 'alert-info';
                    if ($mesaSugeridaEstado === 'disponible') $alertClass = 'alert-success';
                    elseif ($mesaSugeridaEstado === 'ocupada' || $mesaSugeridaEstado === 'no_divipol' || $mesaSugeridaEstado === 'fuera_rango') $alertClass = 'alert-warning';
                @endphp
                <div class="alert {{ $alertClass }} py-2">
                    <strong>Mesa sugerida por formulario:</strong> {{ $mesaSugerida }}
                    @if (!empty($mesaSugeridaDetalle))
                        <br>{{ $mesaSugeridaDetalle }}
                    @endif
                </div>
            @endif

            <form action="{{ route('admin.referidos.asignar', $referido->id) }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="puesto_id">Puesto de votación</label>
                    <select name="puesto_id" id="puesto_id" class="form-control" required>
                        @foreach (($puestos ?? collect()) as $p)
                            <option value="{{ $p->id }}"
                                data-mesas="{{ $p->mesas_total }}"
                                {{ (string) old('puesto_id', $puesto->id ?? $referido->eleccion_puesto_id) === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->municipio }} - {{ $p->puesto }} (mesas: {{ $p->mesas_total }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="mesa_num">Mesa</label>
                    <select name="mesa_num" id="mesa_num" class="form-control" required {{ ($mesasDisponibles ?? collect())->isEmpty() ? 'disabled' : '' }}>
                        <option value="">Seleccione una mesa disponible</option>
                        @foreach (($mesasDisponibles ?? collect()) as $mesa)
                            <option value="{{ $mesa }}" {{ (string) old('mesa_num', $mesaSugerida ?? '') === (string) $mesa ? 'selected' : '' }}>
                                Mesa {{ $mesa }}
                            </option>
                        @endforeach
                    </select>
                    @if (($mesasDisponibles ?? collect())->isEmpty())
                        <small class="text-danger">No hay mesas disponibles en este puesto.</small>
                    @endif
                </div>
                @if (($mesasDisponibles ?? collect())->isEmpty() && ($mesasOcupadasDetalle ?? collect())->isNotEmpty())
                    <div class="alert alert-warning" id="ocupadas_alert">
                        <strong>Mesas ocupadas detectadas (testigo_mesa activo):</strong>
                        <ul class="mb-0 mt-2" id="ocupadas_list">
                            @foreach (($mesasOcupadasDetalle ?? collect()) as $oc)
                                <li>
                                    Mesa {{ $oc->mesa_num ?? 'N/D' }}:
                                    {{ $oc->nombre ?: 'N/D' }} ({{ $oc->cedula ?: 'N/D' }})
                                    [asignacion #{{ $oc->asignacion_id }}]
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <button type="submit" id="btn_asignar" class="btn btn-primary" {{ ($mesasDisponibles ?? collect())->isEmpty() ? 'disabled' : '' }}>Asignar</button>
                <a href="{{ route('admin.referidos.index') }}" class="btn btn-secondary">Volver</a>
            </form>
        </div>
    </div>
@stop

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            $('#puesto_id').select2({ width: '100%' });

            const mesasUrlBase = @json(route('admin.referidos.mesas_disponibles', $referido->id));
            const mesaSelect = document.getElementById('mesa_num');
            const btnAsignar = document.getElementById('btn_asignar');
            const maxEl = document.getElementById('mesas_max');
            const alertEl = document.getElementById('ocupadas_alert');
            const listEl = document.getElementById('ocupadas_list');

            function setMesaOptions(results, selected) {
                mesaSelect.innerHTML = '<option value=\"\">Seleccione una mesa disponible</option>';
                (results || []).forEach(function (r) {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.text;
                    if (selected && String(selected) === String(r.id)) opt.selected = true;
                    mesaSelect.appendChild(opt);
                });
                const has = (results || []).length > 0;
                mesaSelect.disabled = !has;
                btnAsignar.disabled = !has;
            }

            function renderOcupadas(ocupadas) {
                if (!alertEl || !listEl) return;
                listEl.innerHTML = '';
                if (!ocupadas || !ocupadas.length) {
                    alertEl.style.display = 'none';
                    return;
                }
                ocupadas.forEach(function (o) {
                    const li = document.createElement('li');
                    li.textContent = 'Mesa ' + (o.mesa_num ?? 'N/D') + ': ' + (o.nombre || 'N/D') + ' (' + (o.cedula || 'N/D') + ') [asignacion #' + (o.asignacion_id || 'N/D') + ']';
                    listEl.appendChild(li);
                });
                alertEl.style.display = '';
            }

            async function refreshMesas(selectedMesa = null) {
                const puestoSelect = document.getElementById('puesto_id');
                const puestoId = puestoSelect?.value;
                if (!puestoId) return;
                try {
                    const res = await fetch(mesasUrlBase + '?puesto_id=' + encodeURIComponent(puestoId), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    setMesaOptions(data.results || [], selectedMesa);
                    maxEl.textContent = String(data.max || 0);
                    renderOcupadas(data.ocupadas || []);
                } catch (e) {
                    setMesaOptions([], null);
                }
            }

            document.getElementById('puesto_id')?.addEventListener('change', function () {
                refreshMesas(null);
            });

            refreshMesas(@json(old('mesa_num', $mesaSugerida ?? null)));
        });
    </script>
@endsection
