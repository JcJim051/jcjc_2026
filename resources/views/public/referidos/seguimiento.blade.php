<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento Referidos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
    <style>
        :root { --brand-primary:#0f4c81; --brand-bg-1:#f3f8ff; --brand-bg-2:#e8f5f0; }
        body { background: linear-gradient(130deg, var(--brand-bg-1), var(--brand-bg-2)); min-height:100vh; color:#17314b; }
        .brand-wrap { max-width:1220px; margin:30px auto; padding:0 12px; }
        .brand-card { border:0; border-radius:16px; box-shadow:0 12px 34px rgba(15,76,129,.16); overflow:hidden; background:rgba(255,255,255,.96); }
        .brand-header { background: linear-gradient(120deg, var(--brand-primary), #1f6aa8); color:#fff; padding:20px 24px 16px; }
        .brand-title-row { display:flex; align-items:center; gap:10px; }
        .brand-logo-wrap { width:44px; height:44px; border-radius:10px; display:grid; place-items:center; background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.28); }
        .brand-logo { width:30px; height:30px; object-fit:contain; display:block; }
        .brand-body { padding:20px 24px 24px; }
        .top-action { display:flex; justify-content:flex-end; margin-bottom:10px; }
        .btn-referir { background:#0f4c81; color:#fff; font-weight:700; border-radius:10px; padding:8px 14px; text-decoration:none; }
        .btn-referir:hover { color:#fff; background:#0c406c; text-decoration:none; }
        .table-wrap { border-radius:12px; overflow:hidden; border:1px solid #e5edf5; }
        .summary-grid { margin-bottom:14px; }
        .summary-grid h5 { margin:0 0 10px; color:#123a5f; font-weight:700; }
        .summary-table th, .summary-table td, table.dataTable thead th, table.dataTable tbody td { white-space:nowrap; vertical-align:middle; }
        .table thead th { background:#f0f6fd; color:#153554; border-bottom:0; font-weight:700; }
        .table td { background:#fff; }
        .badge-estado { padding:4px 10px; border-radius:999px; font-weight:700; font-size:.75rem; letter-spacing:.2px; text-transform:uppercase; }
        .badge-referido { background:#d8ecff; color:#0f4c81; }
        .badge-asignado, .badge-validado { background:#dbf5ea; color:#0f7a53; }
        .badge-rechazado { background:#ffe1e1; color:#b62929; }
        .badge-default { background:#eceff4; color:#4a5568; }
        .badge-alerta { padding:4px 10px; border-radius:999px; font-weight:700; font-size:.72rem; letter-spacing:.2px; text-transform:uppercase; background:#ffe3c7; color:#8a3d00; }
        .btn-edit { background:#0f4c81; color:#fff; font-size:.75rem; padding:4px 8px; border-radius:8px; font-weight:600; }
        .btn-edit:hover { color:#fff; background:#0c406c; }
        .brand-footer { margin-top:16px; background:rgba(255,255,255,.9); border-radius:12px; text-align:center; color:var(--brand-primary); font-weight:600; font-size:.9rem; padding:10px 12px; box-shadow:0 8px 20px rgba(15,76,129,.08); }
        .brand-footer a { color:inherit; text-decoration:none; }
        .brand-footer a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="brand-wrap">
    <div class="brand-card">
        <div class="brand-header">
            <div class="brand-title-row">
                <div class="brand-logo-wrap"><img class="brand-logo" src="{{ asset('img/logo.png') }}" alt="Logo TestiApp"></div>
                <h4>Seguimiento de Referidos</h4>
            </div>
        </div>
        <div class="brand-body">
            @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if (session('error')) <div class="alert alert-warning">{{ session('error') }}</div> @endif

            @if (empty($token->es_consulta))
                <div class="top-action"><a class="btn-referir" href="{{ route('public.referidos.form', $token->token) }}">Referir Testigo</a></div>
            @endif

            <p class="mb-3"><strong>Municipio:</strong> {{ $municipio ?? 'N/D' }} | <strong>Comuna:</strong> {{ $token->comuna ?: 'Todas' }}</p>

            @php
                $resumenMunicipios = [];
                foreach (($puestos_alcance ?? collect()) as $p) {
                    $mun = (string) ($p->municipio ?? 'N/D');
                    if (!isset($resumenMunicipios[$mun])) {
                        $resumenMunicipios[$mun] = [
                            'municipio' => $mun,
                            'meta' => 0,
                            'registrados' => 0,
                            'asignado' => 0,
                            'validado' => 0,
                            'faltan' => 0,
                            'puestos' => [],
                        ];
                    }
                    $resumenMunicipios[$mun]['meta'] += (int) ($p->meta_objetivo ?? 0);
                    $resumenMunicipios[$mun]['registrados'] += (int) ($p->total_referidos ?? 0);
                    $resumenMunicipios[$mun]['asignado'] += (int) ($p->c_asignado ?? 0);
                    $resumenMunicipios[$mun]['validado'] += (int) ($p->c_validado ?? 0);
                    $resumenMunicipios[$mun]['faltan'] += (int) ($p->faltantes ?? 0);
                    $resumenMunicipios[$mun]['puestos'][] = [
                        'puesto' => (string) ($p->puesto ?? 'N/D'),
                        'meta' => (int) ($p->meta_objetivo ?? 0),
                        'registrados' => (int) ($p->total_referidos ?? 0),
                        'asignado' => (int) ($p->c_asignado ?? 0),
                        'validado' => (int) ($p->c_validado ?? 0),
                        'faltan' => (int) ($p->faltantes ?? 0),
                    ];
                }
                $resumenMunicipios = array_values($resumenMunicipios);
            @endphp

            <div class="table-responsive table-wrap summary-grid">
                <h5>Resumen General Por Municipio</h5>
                <table id="summaryTable" class="table table-sm mb-0 summary-table display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Municipio</th>
                            <th>Meta ({{ $meta_pct ?? 100 }}%)</th>
                            <th>Registrados</th>
                            <th>Asignado</th>
                            <th>Validado</th>
                            <th>Faltan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($resumenMunicipios as $m)
                            <tr>
                                <td><button type="button" class="btn btn-sm btn-outline-primary details-btn">Ver</button></td>
                                <td>{{ $m['municipio'] }}</td>
                                <td>{{ $m['meta'] }}</td>
                                <td><strong>{{ $m['registrados'] }}</strong></td>
                                <td>{{ $m['asignado'] }}</td>
                                <td>{{ $m['validado'] }}</td>
                                <td><strong>{{ $m['faltan'] }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No hay puestos en el alcance actual.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (empty($token->es_consulta))
            <div class="table-responsive table-wrap">
                <table id="referidosTable" class="table table-sm mb-0 display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Puesto</th>
                            <th>Mesa</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Alerta</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($referidos as $r)
                            @php
                                $estado = strtolower((string) $r->estado);
                                $badgeClass = 'badge-default';
                                if ($estado === 'referido') $badgeClass = 'badge-referido';
                                elseif ($estado === 'asignado') $badgeClass = 'badge-asignado';
                                elseif ($estado === 'validado') $badgeClass = 'badge-validado';
                                elseif ($estado === 'rechazado') $badgeClass = 'badge-rechazado';
                                $obs = (string) ($r->observaciones ?? '');
                                $tieneCambioPuesto = stripos($obs, 'cambio de puesto') !== false;
                            @endphp
                            <tr>
                                <td>{{ $r->cedula }}</td>
                                <td>{{ $r->nombre }}</td>
                                <td>{{ $r->email }}</td>
                                <td>{{ $r->telefono }}</td>
                                <td>{{ $r->puesto }}</td>
                                <td>{{ $r->mesa_num }}</td>
                                <td><span class="badge-estado {{ $badgeClass }}">{{ $r->estado }}</span></td>
                                <td>{{ $r->observaciones }}</td>
                                <td>@if ($tieneCambioPuesto)<span class="badge-alerta">Cambio de puesto</span>@else<span class="text-muted">-</span>@endif</td>
                                <td>{{ $r->created_at }}</td>
                                <td><a class="btn btn-edit" href="{{ route('public.referidos.edit', [$token->token, $r->id]) }}">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
    <div class="brand-footer">Creado por <a href="https://procesos.shipper.com.co" target="_blank" rel="noopener noreferrer">Jonathan Jimenez</a></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script>
    $(function () {
        var resumenMunicipios = @json($resumenMunicipios);

        function formatPuestos(rows) {
            if (!rows || !rows.length) return '<div class="p-2 text-muted">Sin detalle de puestos.</div>';
            var html = '<div class="p-2"><table class="table table-sm table-bordered mb-0">';
            html += '<thead><tr><th>Puesto</th><th>Meta</th><th>Registrados</th><th>Asignado</th><th>Validado</th><th>Faltan</th></tr></thead><tbody>';
            rows.forEach(function (r) {
                html += '<tr>'
                    + '<td>' + (r.puesto || 'N/D') + '</td>'
                    + '<td>' + (r.meta || 0) + '</td>'
                    + '<td><strong>' + (r.registrados || 0) + '</strong></td>'
                    + '<td>' + (r.asignado || 0) + '</td>'
                    + '<td>' + (r.validado || 0) + '</td>'
                    + '<td><strong>' + (r.faltan || 0) + '</strong></td>'
                    + '</tr>';
            });
            html += '</tbody></table></div>';
            return html;
        }

        var summaryTable = $('#summaryTable').DataTable({
            pageLength: 25,
            responsive: true,
            scrollX: true,
            order: [[1, 'asc']],
            columnDefs: [
                { orderable: false, targets: 0 },
                { responsivePriority: 1, targets: [1, 2, 3] }
            ],
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                paginate: { previous: 'Anterior', next: 'Siguiente' }
            }
        });

        $('#summaryTable tbody').on('click', 'button.details-btn', function () {
            var tr = $(this).closest('tr');
            var row = summaryTable.row(tr);
            var index = row.index();
            var data = resumenMunicipios[index] || {};

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
                $(this).text('Ver');
            } else {
                row.child(formatPuestos(data.puestos || [])).show();
                tr.addClass('shown');
                $(this).text('Ocultar');
            }
        });

        @if (empty($token->es_consulta))
        $('#referidosTable').DataTable({
            pageLength: 25,
            responsive: true,
            scrollX: true,
            order: [[9, 'desc']],
            columnDefs: [
                { responsivePriority: 1, targets: [0, 1, 6, 10] },
                { responsivePriority: 2, targets: [5, 9] }
            ],
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                paginate: { previous: 'Anterior', next: 'Siguiente' }
            }
        });
        @endif
    });
</script>
</body>
</html>
